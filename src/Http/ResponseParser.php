<?php

declare(strict_types=1);

namespace Namecheap\Http;

use Exception;
use JsonException;
use Namecheap\Enums\ResponseFormat;
use Namecheap\Exceptions\ParseException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SimpleXMLElement;

use function count;
use function is_array;

/**
 * Response parser for different formats
 */
readonly class ResponseParser
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Parse response based on requested format
     *
     * @throws ParseException
     *
     * @return array<string, mixed>
     */
    public function parse(string $response, ResponseFormat $format): array
    {
        return match ($format) {
            ResponseFormat::XML,
            ResponseFormat::ARRAY => $this->parseXml($response),
            ResponseFormat::JSON => $this->parseJson($response)
        };
    }

    /**
     * Parse XML response
     *
     * @throws ParseException
     *
     * @return array<string, mixed>
     */
    private function parseXml(string $response): array
    {
        try {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);

            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorMessages = array_map(fn ($error) => trim($error->message), $errors);

                throw new ParseException(
                    'Failed to parse XML response: ' . implode(', ', $errorMessages),
                    0,
                    null,
                    $response,
                );
            }

            return $this->xmlToArray($xml);
        } catch (Exception $e) {
            $this->logger->error('XML parsing failed', [
                'error' => $e->getMessage(),
                'response' => $response,
            ]);

            throw new ParseException(
                'XML parsing failed: ' . $e->getMessage(),
                0,
                $e,
                $response,
            );
        }
    }

    /**
     * Convert SimpleXMLElement to array
     *
     * @return array<string, mixed>
     */
    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $array = [];

        foreach ($xml->attributes() as $key => $value) {
            $array['@' . $key] = (string)$value;
        }

        $children = $xml->children();

        if (count($children) === 0) {
            $value = (string)$xml;
            if ($value !== '') {
                $array['$'] = $value;
            }
        } else {
            foreach ($children as $child) {
                $name = $child->getName();
                $value = $this->xmlToArray($child);

                if (isset($array[$name])) {
                    if (!is_array($array[$name]) || !isset($array[$name][0])) {
                        $array[$name] = [$array[$name]];
                    }
                    $array[$name][] = $value;
                } else {
                    $array[$name] = $value;
                }
            }
        }

        return $array;
    }

    /**
     * Parse JSON response
     *
     * @throws ParseException
     *
     * @return array<string, mixed>
     */
    private function parseJson(string $response): array
    {
        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new ParseException('JSON response is not an array', 0, null, $response);
            }

            return $data;
        } catch (JsonException $e) {
            $this->logger->error('JSON parsing failed', [
                'error' => $e->getMessage(),
                'response' => $response,
            ]);

            throw new ParseException(
                'JSON parsing failed: ' . $e->getMessage(),
                0,
                $e,
                $response,
            );
        }
    }
}
