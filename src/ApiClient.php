<?php

declare(strict_types=1);

namespace Namecheap;

use Namecheap\DataTransferObjects\ApiConfiguration;
use Namecheap\DataTransferObjects\ApiResponse;
use Namecheap\Enums\ResponseFormat;
use Namecheap\Exceptions\ApiException;
use Namecheap\Exceptions\AuthenticationException;
use Namecheap\Exceptions\ConfigurationException;
use Namecheap\Exceptions\NetworkException;
use Namecheap\Exceptions\ParseException;
use Namecheap\Exceptions\ValidationException;
use Namecheap\Http\HttpClient;
use Namecheap\Http\ResponseParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function is_array;
use function sprintf;

/**
 * Main Namecheap API client
 */
readonly class ApiClient
{
    private HttpClient      $httpClient;

    private ResponseParser  $responseParser;

    private LoggerInterface $logger;

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        private ApiConfiguration $config,
        ?LoggerInterface         $logger = null,
    ) {
        $this->validateConfiguration($config);

        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = new HttpClient($config, $this->logger);
        $this->responseParser = new ResponseParser($this->logger);
    }

    /**
     * Factory method to create API client from parameters
     *
     * @param array<int, mixed> $curlOptions
     *
     * @throws ConfigurationException
     */
    public static function create(
        string           $apiUser,
        string           $apiKey,
        string           $userName,
        string           $clientIp,
        bool             $sandbox = false,
        int              $timeout = 30,
        array            $curlOptions = [],
        ?LoggerInterface $logger = null,
    ): self {
        $config = new ApiConfiguration(
            apiUser    : $apiUser,
            apiKey     : $apiKey,
            userName   : $userName,
            clientIp   : $clientIp,
            sandbox    : $sandbox,
            timeout    : $timeout,
            curlOptions: $curlOptions,
        );

        return new self($config, $logger);
    }

    /**
     * Make API request
     *
     * @param array<string, mixed> $parameters
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function request(
        string         $command,
        array          $parameters = [],
        ResponseFormat $responseFormat = ResponseFormat::XML,
    ): ApiResponse {
        $requestParameters = $this->buildRequestParameters($command, $parameters);

        $rawResponse = $this->httpClient->post($requestParameters);
        $parsedResponse = $this->responseParser->parse($rawResponse, $responseFormat);

        return $this->processApiResponse($parsedResponse, $command);
    }

    /**
     * Get the API configuration
     */
    public function getConfiguration(): ApiConfiguration
    {
        return $this->config;
    }

    /**
     * Enable sandbox mode
     *
     * @throws ConfigurationException
     */
    public function enableSandbox(): self
    {
        $newConfig = new ApiConfiguration(
            apiUser    : $this->config->apiUser,
            apiKey     : $this->config->apiKey,
            userName   : $this->config->userName,
            clientIp   : $this->config->clientIp,
            sandbox    : true,
            timeout    : $this->config->timeout,
            curlOptions: $this->config->curlOptions,
        );

        return new self($newConfig, $this->logger);
    }

    /**
     * Set custom cURL option
     */
    public function setCurlOption(int $option, mixed $value): self
    {
        $curlOptions = $this->config->curlOptions;
        $curlOptions[$option] = $value;

        $newConfig = new ApiConfiguration(
            apiUser    : $this->config->apiUser,
            apiKey     : $this->config->apiKey,
            userName   : $this->config->userName,
            clientIp   : $this->config->clientIp,
            sandbox    : $this->config->sandbox,
            timeout    : $this->config->timeout,
            curlOptions: $curlOptions,
        );

        return new self($newConfig, $this->logger);
    }

    /**
     * Validate the API configuration
     *
     * @throws ConfigurationException
     */
    private function validateConfiguration(ApiConfiguration $config): void
    {
        if (empty($config->apiUser)) {
            throw new ConfigurationException('API user is required');
        }

        if (empty($config->apiKey)) {
            throw new ConfigurationException('API key is required');
        }

        if (empty($config->userName)) {
            throw new ConfigurationException('Username is required');
        }

        if (empty($config->clientIp)) {
            throw new ConfigurationException('Client IP is required');
        }

        if (!filter_var($config->clientIp, FILTER_VALIDATE_IP)) {
            throw new ConfigurationException('Invalid client IP address');
        }

        if ($config->timeout <= 0) {
            throw new ConfigurationException('Timeout must be greater than 0');
        }
    }

    /**
     * Build request parameters with authentication
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function buildRequestParameters(string $command, array $parameters): array
    {
        return array_merge([
            'ApiUser' => $this->config->apiUser,
            'ApiKey' => $this->config->apiKey,
            'UserName' => $this->config->userName,
            'Command' => $command,
            'ClientIp' => $this->config->clientIp,
        ], $parameters);
    }

    /**
     * Process API response and handle errors
     *
     * @param array<string, mixed> $response
     *
     * @throws ApiException|AuthenticationException|ValidationException
     */
    private function processApiResponse(array $response, string $command): ApiResponse
    {
        $status = $response['@Status'] ?? 'ERROR';
        $errors = $this->extractErrors($response);
        $warnings = $this->extractWarnings($response);

        if ($status !== 'OK' || !empty($errors)) {
            $this->handleApiErrors($errors, $command);
        }

        $data = $response['CommandResponse'] ?? [];
        $server = $response['Server']['$'] ?? null;
        $executionTime = isset($response['ExecutionTime']['$'])
            ? (float)$response['ExecutionTime']['$']
            : null;
        $gmtTimeDifference = $response['GMTTimeDifference']['$'] ?? null;

        return new ApiResponse(
            success          : $status === 'OK',
            command          : $command,
            data             : $data,
            errors           : $errors,
            warnings         : $warnings,
            server           : $server,
            executionTime    : $executionTime,
            gmtTimeDifference: $gmtTimeDifference,
        );
    }

    /**
     * Extract errors from API response
     *
     * @param array<string, mixed> $response
     *
     * @return array<string>
     */
    private function extractErrors(array $response): array
    {
        $errors = [];
        $errorSection = $response['Errors'] ?? [];

        if (isset($errorSection['Error'])) {
            $errorData = $errorSection['Error'];

            // Handle single error
            if (isset($errorData['@Number'])) {
                $errors[] = sprintf(
                    '[%s] %s',
                    $errorData['@Number'],
                    $errorData['$'] ?? 'Unknown error',
                );
            } else {
                // Handle multiple errors
                foreach ($errorData as $error) {
                    if (is_array($error) && isset($error['@Number'])) {
                        $errors[] = sprintf(
                            '[%s] %s',
                            $error['@Number'],
                            $error['$'] ?? 'Unknown error',
                        );
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Extract warnings from API response
     *
     * @param array<string, mixed> $response
     *
     * @return array<string>
     */
    private function extractWarnings(array $response): array
    {
        $warnings = [];
        $warningSection = $response['Warnings'] ?? [];

        if (isset($warningSection['Warning'])) {
            $warningData = $warningSection['Warning'];

            // Handle single warning
            if (isset($warningData['@Number'])) {
                $warnings[] = sprintf(
                    '[%s] %s',
                    $warningData['@Number'],
                    $warningData['$'] ?? 'Unknown warning',
                );
            } else {
                // Handle multiple warnings
                foreach ($warningData as $warning) {
                    if (is_array($warning) && isset($warning['@Number'])) {
                        $warnings[] = sprintf(
                            '[%s] %s',
                            $warning['@Number'],
                            $warning['$'] ?? 'Unknown warning',
                        );
                    }
                }
            }
        }

        return $warnings;
    }

    /**
     * Handle API errors and throw appropriate exceptions
     *
     * @param array<string> $errors
     *
     * @throws ApiException|AuthenticationException|ValidationException
     */
    private function handleApiErrors(array $errors, string $command): void
    {
        if (empty($errors)) {
            return;
        }

        $primaryError = $errors[0];
        $this->logger->error('API error occurred', [
            'command' => $command,
            'errors' => $errors,
        ]);

        // Check for authentication errors
        if (str_contains(strtolower($primaryError), 'authentication') ||
            str_contains(strtolower($primaryError), 'unauthorized') ||
            str_contains(strtolower($primaryError), 'invalid api key')) {
            throw new AuthenticationException($primaryError, 401, null, [
                'command' => $command,
                'all_errors' => $errors,
            ]);
        }

        // Check for validation errors
        if (str_contains(strtolower($primaryError), 'validation') ||
            str_contains(strtolower($primaryError), 'invalid parameter') ||
            str_contains(strtolower($primaryError), 'required parameter')) {
            throw new ValidationException($primaryError, 400, null, $errors, [
                'command' => $command,
            ]);
        }

        // Default to generic API exception
        throw new ApiException($primaryError, 500, null, null, [
            'command' => $command,
            'all_errors' => $errors,
        ]);
    }
}
