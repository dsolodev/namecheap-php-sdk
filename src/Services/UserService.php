<?php

declare(strict_types=1);

namespace Namecheap\Services;

use Namecheap\ApiClient;
use Namecheap\DataTransferObjects\DomainPricing;
use Namecheap\DataTransferObjects\UserBalance;
use Namecheap\Enums\PricingType;
use Namecheap\Enums\ResponseFormat;
use Namecheap\Exceptions\ApiException;
use Namecheap\Exceptions\AuthenticationException;
use Namecheap\Exceptions\NetworkException;
use Namecheap\Exceptions\ParseException;
use Namecheap\Exceptions\ValidationException;

use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * User account management service
 */
readonly class UserService
{
    public function __construct(
        private ApiClient $apiClient,
    ) {
    }

    /**
     * Get user account balances
     *
     * @throws ApiException
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     */
    public function getBalances(): UserBalance
    {
        $response = $this->apiClient->request(
            'namecheap.users.getBalances',
            [],
            ResponseFormat::ARRAY,
        );

        return $this->parseUserBalance($response->data);
    }

    /**
     * Get pricing information
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     *
     * @return array<DomainPricing>
     */
    public function getPricing(PricingType $type, ?string $productType = null): array
    {
        $parameters = [
            'ProductType' => $type->value,
        ];

        if ($productType !== null) {
            $parameters['ProductName'] = $productType;
        }

        $response = $this->apiClient->request(
            'namecheap.users.getPricing',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parsePricing($response->data);
    }

    /**
     * Change user password
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function changePassword(
        string $oldPassword,
        string $newPassword,
        bool   $isResetCode = false,
    ): bool {
        $parameters = [
            'NewPassword' => $newPassword,
        ];

        if ($isResetCode) {
            $parameters['ResetCode'] = $oldPassword;
        } else {
            $parameters['OldPassword'] = $oldPassword;
        }

        $response = $this->apiClient->request(
            'namecheap.users.changePassword',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Update user information
     *
     * @param array<string, mixed> $userInfo
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function update(array $userInfo): bool
    {
        $validFields = [
            'FirstName',
            'LastName',
            'JobTitle',
            'Organization',
            'Address1',
            'Address2',
            'City',
            'StateProvince',
            'PostalCode',
            'Country',
            'Phone',
            'PhoneExt',
            'Fax',
        ];

        $parameters = array_filter($userInfo, fn ($field) => in_array($field, $validFields, true), ARRAY_FILTER_USE_KEY);

        if (empty($parameters)) {
            throw new ValidationException('No valid user information fields provided');
        }

        $response = $this->apiClient->request(
            'namecheap.users.update',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Create a new user account
     *
     * @param array<string, mixed> $userInfo
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function create(array $userInfo): bool
    {
        $requiredFields = [
            'NewUserName',
            'NewUserPassword',
            'EmailAddress',
            'FirstName',
            'LastName',
            'Address1',
            'City',
            'StateProvince',
            'PostalCode',
            'Country',
            'Phone',
        ];

        foreach ($requiredFields as $field) {
            if (empty($userInfo[$field])) {
                throw new ValidationException("Required field '{$field}' is missing or empty");
            }
        }

        $response = $this->apiClient->request(
            'namecheap.users.create',
            $userInfo,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Login to user account
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function login(string $password): bool
    {
        $parameters = [
            'Password' => $password,
        ];

        $response = $this->apiClient->request(
            'namecheap.users.login',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Reset user password
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function resetPassword(string $findBy, string $findByValue): bool
    {
        $parameters = [
            'FindBy' => $findBy, // EMAIL or USERNAME
            $findBy => $findByValue,
        ];

        $response = $this->apiClient->request(
            'namecheap.users.resetPassword',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Parse user balance from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws ApiException
     */
    private function parseUserBalance(array $data): UserBalance
    {
        $balanceInfo = $data['UserGetBalancesResult'] ?? [];

        if (!is_array($balanceInfo)) {
            throw new ApiException('Invalid user balance response format');
        }

        $availableBalance = $this->getFloatValue($balanceInfo, '@AvailableBalance');
        $currency = $this->getStringValue($balanceInfo, '@Currency') ?: 'USD';
        $accountBalance = $this->getFloatValue($balanceInfo, '@AccountBalance');
        $earnedAmount = $this->getFloatValue($balanceInfo, '@EarnedAmount');
        $withdrawableAmount = $this->getFloatValue($balanceInfo, '@WithdrawableAmount');
        $fundsRequiredForAutoRenew = $this->getFloatValue($balanceInfo, '@FundsRequiredForAutoRenew');

        return new UserBalance(
            availableBalance         : $availableBalance,
            currency                 : $currency,
            accountBalance           : $accountBalance,
            earnedAmount             : $earnedAmount,
            withdrawableAmount       : $withdrawableAmount,
            fundsRequiredForAutoRenew: $fundsRequiredForAutoRenew,
        );
    }

    /**
     * Safely get float value from array
     *
     * @param array<string, mixed> $data
     */
    private function getFloatValue(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float)$value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float)$value;
        }

        return 0.0;
    }

    /**
     * Safely get string value from array
     *
     * @param array<string, mixed> $data
     */
    private function getStringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * Parse pricing information from API response
     *
     * @param array<string, mixed> $data
     *
     * @return array<DomainPricing>
     */
    private function parsePricing(array $data): array
    {
        $pricing = [];
        $pricingResult = $data['UserGetPricingResult'] ?? [];

        if (!is_array($pricingResult)) {
            return [];
        }

        if (isset($pricingResult['ProductType'])) {
            $productTypes = $pricingResult['ProductType'];

            if (!is_array($productTypes)) {
                return [];
            }

            // Handle single product type
            if (isset($productTypes['@Name'])) {
                $pricing = array_merge($pricing, $this->parseProductTypePricing($productTypes));
            } else {
                // Handle multiple product types
                foreach ($productTypes as $productType) {
                    if (is_array($productType) && isset($productType['@Name'])) {
                        $pricing = array_merge($pricing, $this->parseProductTypePricing($productType));
                    }
                }
            }
        }

        return $pricing;
    }

    /**
     * Parse pricing for a specific product type
     *
     * @param array<string, mixed> $productType
     *
     * @return array<DomainPricing>
     */
    private function parseProductTypePricing(array $productType): array
    {
        $pricing = [];

        if (isset($productType['Product'])) {
            $products = $productType['Product'];

            if (!is_array($products)) {
                return [];
            }

            // Handle single product
            if (isset($products['@Name'])) {
                $domainPricing = $this->createDomainPricingFromArray($products);
                if ($domainPricing !== null) {
                    $pricing[] = $domainPricing;
                }
            } else {
                // Handle multiple products
                foreach ($products as $product) {
                    if (is_array($product) && isset($product['@Name'])) {
                        $domainPricing = $this->createDomainPricingFromArray($product);
                        if ($domainPricing !== null) {
                            $pricing[] = $domainPricing;
                        }
                    }
                }
            }
        }

        return $pricing;
    }

    /**
     * Create DomainPricing object from array data
     *
     * @param array<string, mixed> $data
     */
    private function createDomainPricingFromArray(array $data): ?DomainPricing
    {
        $tld = $this->getStringValue($data, '@Name');
        if ($tld === '') {
            return null;
        }

        $price = $data['Price'] ?? [];
        if (!is_array($price)) {
            return null;
        }

        $registerPrice = $this->getFloatValue($price, '@RegisterPrice');
        $renewPrice = $this->getFloatValue($price, '@RenewPrice');
        $transferPrice = $this->getFloatValue($price, '@TransferPrice');
        $restorePrice = $this->getFloatValue($price, '@RestorePrice');
        $currency = $this->getStringValue($price, '@Currency') ?: 'USD';
        $yearsMin = $this->getIntValue($price, '@YearsMin') ?? 1;
        $yearsMax = $this->getIntValue($price, '@YearsMax') ?? 10;

        return new DomainPricing(
            tld          : $tld,
            registerPrice: $registerPrice,
            renewPrice   : $renewPrice,
            transferPrice: $transferPrice,
            restorePrice : $restorePrice,
            currency     : $currency,
            yearsMin     : $yearsMin,
            yearsMax     : $yearsMax,
        );
    }

    /**
     * Safely get integer value from array
     *
     * @param array<string, mixed> $data
     */
    private function getIntValue(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }
}
