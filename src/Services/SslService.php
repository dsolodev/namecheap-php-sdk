<?php

declare(strict_types=1);

namespace Namecheap\Services;

use DateTimeImmutable;
use Exception;
use Namecheap\ApiClient;
use Namecheap\DataTransferObjects\SslCertificate;
use Namecheap\Enums\ResponseFormat;
use Namecheap\Enums\SslType;
use Namecheap\Exceptions\ApiException;
use Namecheap\Exceptions\AuthenticationException;
use Namecheap\Exceptions\NetworkException;
use Namecheap\Exceptions\ParseException;
use Namecheap\Exceptions\ValidationException;

use function is_array;
use function is_string;

/**
 * SSL certificate management service
 */
readonly class SslService
{
    public function __construct(
        private ApiClient $apiClient,
    ) {
    }

    /**
     * Get list of SSL certificates
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ApiException
     *
     * @return array<SslCertificate>
     */
    public function getList(
        string $listType = 'All',
        string $searchTerm = '',
        int    $page = 1,
        int    $pageSize = 20,
    ): array {
        $parameters = [
            'ListType' => $listType,
            'SearchTerm' => $searchTerm,
            'Page' => $page,
            'PageSize' => $pageSize,
        ];

        $response = $this->apiClient->request(
            'namecheap.ssl.getList',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseSslCertificateList($response->data);
    }

    /**
     * Create (purchase) a new SSL certificate
     *
     * @param array<string> $sanDomains
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function create(
        SslType $type,
        int     $years,
        string  $csr,
        string  $adminEmail,
        array   $sanDomains = [],
        string  $promotionCode = '',
    ): string {
        $parameters = [
            'Type' => $type->value,
            'Years' => $years,
            'CSR' => $csr,
            'AdminEmailAddress' => $adminEmail,
            'PromotionCode' => $promotionCode,
        ];

        if (!empty($sanDomains)) {
            $parameters['SANStoAdd'] = implode(',', $sanDomains);
        }

        $response = $this->apiClient->request(
            'namecheap.ssl.create',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->extractCertificateId($response->data);
    }

    /**
     * Activate an SSL certificate
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function activate(
        string $certificateId,
        string $csr,
        string $adminEmail,
        string $webServerType = 'apachessl',
    ): bool {
        $parameters = [
            'CertificateID' => $certificateId,
            'CSR' => $csr,
            'AdminEmailAddress' => $adminEmail,
            'WebServerType' => $webServerType,
        ];

        $response = $this->apiClient->request(
            'namecheap.ssl.activate',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Get SSL certificate information
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function getInfo(string $certificateId): SslCertificate
    {
        $parameters = [
            'CertificateID' => $certificateId,
        ];

        $response = $this->apiClient->request(
            'namecheap.ssl.getInfo',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseSslCertificateInfo($response->data);
    }

    /**
     * Renew an SSL certificate
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function renew(
        string  $certificateId,
        int     $years,
        SslType $type,
        string  $promotionCode = '',
    ): string {
        $parameters = [
            'CertificateID' => $certificateId,
            'Years' => $years,
            'Type' => $type->value,
            'PromotionCode' => $promotionCode,
        ];

        $response = $this->apiClient->request(
            'namecheap.ssl.renew',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->extractCertificateId($response->data);
    }

    /**
     * Reissue an SSL certificate
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function reissue(
        string $certificateId,
        string $csr,
        string $adminEmail,
        string $webServerType = 'apachessl',
    ): bool {
        $parameters = [
            'CertificateID' => $certificateId,
            'CSR' => $csr,
            'AdminEmailAddress' => $adminEmail,
            'WebServerType' => $webServerType,
        ];

        $response = $this->apiClient->request(
            'namecheap.ssl.reissue',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Get approver email list for domain validation
     *
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     * @throws ApiException
     *
     * @return array<string>
     */
    public function getApproverEmailList(string $domainName, string $certificateType): array
    {
        $parameters = [
            'DomainName' => $domainName,
            'CertificateType' => $certificateType,
        ];

        $response = $this->apiClient->request(
            'namecheap.ssl.getApproverEmailList',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseApproverEmailList($response->data);
    }

    /**
     * Parse SSL certificate list from API response
     *
     * @param array<string, mixed> $data
     *
     * @return array<SslCertificate>
     */
    private function parseSslCertificateList(array $data): array
    {
        $certificates = [];
        $sslList = $data['SSLGetListResult'] ?? [];

        if (!is_array($sslList)) {
            return [];
        }

        if (isset($sslList['SSL'])) {
            $sslData = $sslList['SSL'];

            if (!is_array($sslData)) {
                return [];
            }

            // Handle single certificate
            if (isset($sslData['@CertificateID'])) {
                $certificate = $this->createSslCertificateFromArray($sslData);
                if ($certificate !== null) {
                    $certificates[] = $certificate;
                }
            } else {
                // Handle multiple certificates
                foreach ($sslData as $ssl) {
                    if (is_array($ssl) && isset($ssl['@CertificateID'])) {
                        $certificate = $this->createSslCertificateFromArray($ssl);
                        if ($certificate !== null) {
                            $certificates[] = $certificate;
                        }
                    }
                }
            }
        }

        return $certificates;
    }

    /**
     * Create SslCertificate object from array data
     *
     * @param array<string, mixed> $data
     */
    private function createSslCertificateFromArray(array $data): ?SslCertificate
    {
        $certificateId = $this->getStringValue($data, '@CertificateID');
        $type = $this->getStringValue($data, '@SSLType');
        $status = $this->getStringValue($data, '@Status');
        $createdValue = $this->getStringValue($data, '@Created') ?: 'now';
        $expiresValue = $this->getStringValue($data, '@Expires') ?: 'now';
        $commonName = $this->getStringValue($data, '@HostName');

        if ($certificateId === '') {
            return null;
        }

        $sanDomains = [];
        if (isset($data['SANSDetails']) && is_array($data['SANSDetails'])) {
            foreach ($data['SANSDetails'] as $san) {
                if (is_string($san)) {
                    $sanDomains[] = $san;
                } elseif (is_array($san)) {
                    $sanValue = $this->getStringValue($san, '$');
                    if ($sanValue !== '') {
                        $sanDomains[] = $sanValue;
                    }
                }
            }
        }

        try {
            $createdDate = new DateTimeImmutable($createdValue);
        } catch (Exception) {
            $createdDate = new DateTimeImmutable();
        }

        try {
            $expirationDate = new DateTimeImmutable($expiresValue);
        } catch (Exception) {
            $expirationDate = new DateTimeImmutable();
        }

        $organizationName = $this->getStringValue($data, '@OrganizationName') ?: null;
        $organizationUnit = $this->getStringValue($data, '@OrganizationUnit') ?: null;
        $locality = $this->getStringValue($data, '@Locality') ?: null;
        $stateProvince = $this->getStringValue($data, '@StateProvince') ?: null;
        $country = $this->getStringValue($data, '@Country') ?: null;

        return new SslCertificate(
            certificateId   : $certificateId,
            type            : $type,
            status          : $status,
            createdDate     : $createdDate,
            expirationDate  : $expirationDate,
            commonName      : $commonName,
            sanDomains      : $sanDomains,
            organizationName: $organizationName,
            organizationUnit: $organizationUnit,
            locality        : $locality,
            stateProvince   : $stateProvince,
            country         : $country,
        );
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
     * Extract certificate ID from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws ApiException
     */
    private function extractCertificateId(array $data): string
    {
        $createResult = $data['SSLCreateResult'] ?? [];
        if (is_array($createResult)) {
            $certId = $this->getStringValue($createResult, '@CertificateID');
            if ($certId !== '') {
                return $certId;
            }
        }

        $renewResult = $data['SSLRenewResult'] ?? [];
        if (is_array($renewResult)) {
            $certId = $this->getStringValue($renewResult, '@CertificateID');
            if ($certId !== '') {
                return $certId;
            }
        }

        throw new ApiException('Certificate ID not found in response');
    }

    /**
     * Parse SSL certificate info from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws ApiException
     */
    private function parseSslCertificateInfo(array $data): SslCertificate
    {
        $sslInfo = $data['SSLGetInfoResult'] ?? [];

        if (!is_array($sslInfo)) {
            throw new ApiException('Invalid SSL certificate info response format');
        }

        $certificate = $this->createSslCertificateFromArray($sslInfo);

        if ($certificate === null) {
            throw new ApiException('Failed to parse SSL certificate information');
        }

        return $certificate;
    }

    /**
     * Parse approver email list from API response
     *
     * @param array<string, mixed> $data
     *
     * @return array<string>
     */
    private function parseApproverEmailList(array $data): array
    {
        $emails = [];
        $emailList = $data['Approver'] ?? [];

        if (is_string($emailList)) {
            $emails[] = $emailList;
        } elseif (is_array($emailList)) {
            foreach ($emailList as $email) {
                if (is_string($email)) {
                    $emails[] = $email;
                } elseif (is_array($email)) {
                    $emailValue = $this->getStringValue($email, '$');
                    if ($emailValue !== '') {
                        $emails[] = $emailValue;
                    }
                }
            }
        }

        return $emails;
    }
}
