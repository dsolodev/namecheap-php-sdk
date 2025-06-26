<?php

declare(strict_types=1);

namespace Namecheap\Http;

use CurlHandle;
use Namecheap\DataTransferObjects\ApiConfiguration;
use Namecheap\Enums\Environment;
use Namecheap\Exceptions\NetworkException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function sprintf;
use function strlen;

/**
 * HTTP client for making requests to Namecheap API
 */
readonly class HttpClient
{
    private string          $apiUrl;

    private LoggerInterface $logger;

    public function __construct(
        private ApiConfiguration $config,
        ?LoggerInterface         $logger = null,
    ) {
        $environment = $config->sandbox ? Environment::SANDBOX : Environment::PRODUCTION;
        $this->apiUrl = $environment->getApiUrl();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Make a POST request to the Namecheap API
     *
     * @param array<string, mixed> $parameters
     *
     * @throws NetworkException
     */
    public function post(array $parameters): string
    {
        $curl = $this->initializeCurl($parameters);

        $this->logger->debug('Making API request', [
            'url' => $this->apiUrl,
            'command' => $parameters['Command'] ?? 'unknown',
            'parameters' => array_diff_key($parameters, ['ApiKey' => null]),
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);

        curl_close($curl);

        if ($response === false || $errno !== 0) {
            $this->logger->error('cURL request failed', [
                'error' => $error,
                'errno' => $errno,
                'http_code' => $httpCode,
            ]);

            throw new NetworkException(
                sprintf('cURL request failed: %s (Error %d)', $error, $errno),
                $errno,
            );
        }

        if ($httpCode >= 400) {
            $this->logger->error('HTTP error response', [
                'http_code' => $httpCode,
                'response' => $response,
            ]);

            throw new NetworkException(
                sprintf('HTTP error: %d', $httpCode),
                $httpCode,
            );
        }

        $this->logger->debug('API response received', [
            'http_code' => $httpCode,
            'response_length' => strlen($response),
        ]);

        return $response;
    }

    /**
     * Initialize cURL handle with proper options
     *
     * @param array<string, mixed> $parameters
     *
     * @throws NetworkException
     */
    private function initializeCurl(array $parameters): CurlHandle
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $defaultOptions = [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Namecheap PHP SDK Modern/2.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/xml, application/json, text/xml',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_ENCODING => '', // Accept all supported encodings
        ];

        // Merge with custom cURL options from configuration
        $curlOptions = array_replace($defaultOptions, $this->config->curlOptions);

        curl_setopt_array($curl, $curlOptions);

        return $curl;
    }
}
