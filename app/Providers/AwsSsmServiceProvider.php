<?php

namespace App\Providers;

use Aws\Exception\AwsException;
use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AwsSsmServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (! config('aws-ssm.enabled')) {
            return;
        }

        $this->loadParametersFromSsm();
    }

    /**
     * Load parameters from AWS SSM Parameter Store with caching.
     *
     * @throws RuntimeException If critical parameters cannot be loaded in production
     */
    protected function loadParametersFromSsm(): void
    {
        $cacheTtl = min(config('aws-ssm.cache_ttl', 300), 3600); // Max 1 hour
        $cacheKey = 'aws_ssm_parameters_'.config('app.env'); // Environment-specific cache key

        // Try to get from cache first
        if ($cacheTtl > 0) {
            try {
                $cachedParams = Cache::get($cacheKey);
                if ($cachedParams !== null) {
                    $this->applyParameters($cachedParams);

                    return;
                }
            } catch (\Exception $e) {
                // Cache might not be available yet (e.g., during initial deployment when cache table doesn't exist)
                // Continue to fetch from SSM
                Log::debug('Cache not available, fetching from SSM', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $parameters = $this->fetchParametersFromSsm();

            // Validate that required parameters were loaded
            $this->validateRequiredParameters($parameters);

            // Cache the parameters
            if ($cacheTtl > 0 && ! empty($parameters)) {
                try {
                    Cache::put($cacheKey, $parameters, $cacheTtl);
                } catch (\Exception $e) {
                    // Cache might not be available yet (e.g., during initial deployment)
                    // Continue without caching
                    Log::debug('Unable to cache SSM parameters', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->applyParameters($parameters);

            Log::info('AWS SSM parameters loaded successfully', [
                'count' => count($parameters),
                'environment' => config('app.env'),
            ]);

        } catch (SsmException $e) {
            $this->handleSsmException($e);
        } catch (AwsException $e) {
            $this->handleAwsException($e);
        } catch (RuntimeException $e) {
            // Re-throw validation errors
            throw $e;
        } catch (\Exception $e) {
            $this->handleUnexpectedException($e);
        }
    }

    /**
     * Fetch parameters from AWS SSM Parameter Store.
     *
     * @return array<string, string> Associative array mapping config keys to parameter values
     *
     * @throws SsmException If AWS SSM API call fails
     */
    protected function fetchParametersFromSsm(): array
    {
        $region = config('aws-ssm.region', 'us-east-1');

        $client = new SsmClient([
            'version' => 'latest',
            'region' => $region,
            'http' => [
                'timeout' => 10,
                'connect_timeout' => 5,
            ],
        ]);

        $pathPrefix = rtrim(config('aws-ssm.path_prefix'), '/').'/';
        $parameterMapping = config('aws-ssm.parameters', []);

        if (empty($parameterMapping)) {
            return [];
        }

        // Build list of full parameter names to fetch
        $parameterNames = [];
        foreach ($parameterMapping as $configKey => $ssmParamName) {
            $parameterNames[] = $pathPrefix.$ssmParamName;
        }

        // Pre-build reverse mapping for O(1) lookups
        $reverseMapping = array_flip($parameterMapping);
        $parameters = [];

        // Fetch parameters in batches (AWS limit is 10 per request)
        $batches = array_chunk($parameterNames, 10);

        foreach ($batches as $batch) {
            $result = $client->getParameters([
                'Names' => $batch,
                'WithDecryption' => true,
            ]);

            foreach ($result['Parameters'] as $param) {
                // Extract the parameter name without the prefix
                $paramName = substr($param['Name'], strrpos($param['Name'], '/') + 1);

                // O(1) lookup using reverse mapping
                if (isset($reverseMapping[$paramName])) {
                    $configKey = $reverseMapping[$paramName];
                    $parameters[$configKey] = $param['Value'];
                }
            }

            // Log any invalid parameters
            if (! empty($result['InvalidParameters'])) {
                Log::warning('AWS SSM: Some parameters were not found', [
                    'invalid' => $result['InvalidParameters'],
                    'path_prefix' => $pathPrefix,
                ]);
            }
        }

        return $parameters;
    }

    /**
     * Validate that all required parameters were loaded.
     *
     * @throws RuntimeException If required parameters are missing in production
     */
    protected function validateRequiredParameters(array $parameters): void
    {
        $requiredKeys = config('aws-ssm.parameters', []);
        $missingKeys = [];

        foreach ($requiredKeys as $configKey => $ssmParamName) {
            if (! isset($parameters[$configKey]) || empty($parameters[$configKey])) {
                $missingKeys[] = $ssmParamName;
            }
        }

        if (! empty($missingKeys)) {
            $message = 'AWS SSM: Required parameters are missing: '.implode(', ', $missingKeys);

            Log::critical($message, [
                'missing_parameters' => $missingKeys,
                'environment' => config('app.env'),
            ]);

            // Fail fast in production - missing secrets should prevent app startup
            if (app()->environment('production', 'staging')) {
                throw new RuntimeException($message);
            }
        }
    }

    /**
     * Handle SSM-specific exceptions.
     */
    protected function handleSsmException(SsmException $e): void
    {
        Log::critical('AWS SSM API error', [
            'message' => $e->getMessage(),
            'aws_error_code' => $e->getAwsErrorCode(),
            'aws_error_message' => $e->getAwsErrorMessage(),
            'aws_request_id' => $e->getAwsRequestId(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (app()->environment('production', 'staging')) {
            throw new RuntimeException(
                'Critical: Unable to load required SSM parameters - '.$e->getAwsErrorCode(),
                0,
                $e
            );
        }
    }

    /**
     * Handle general AWS exceptions.
     */
    protected function handleAwsException(AwsException $e): void
    {
        Log::critical('AWS API error while loading SSM parameters', [
            'message' => $e->getMessage(),
            'aws_error_code' => $e->getAwsErrorCode(),
            'trace' => $e->getTraceAsString(),
        ]);

        if (app()->environment('production', 'staging')) {
            throw new RuntimeException(
                'Critical: AWS API error while loading SSM parameters',
                0,
                $e
            );
        }
    }

    /**
     * Handle unexpected exceptions.
     */
    protected function handleUnexpectedException(\Exception $e): void
    {
        Log::critical('Unexpected error loading AWS SSM parameters', [
            'message' => $e->getMessage(),
            'exception_class' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]);

        if (app()->environment('production', 'staging')) {
            throw new RuntimeException(
                'Critical: Unexpected error loading SSM parameters',
                0,
                $e
            );
        }
    }

    /**
     * Apply parameters to Laravel config.
     */
    protected function applyParameters(array $parameters): void
    {
        foreach ($parameters as $configKey => $value) {
            config([$configKey => $value]);
        }
    }
}
