<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Buffer GraphQL API Client
|--------------------------------------------------------------------------
| File: includes/buffer-client.php
|
| Reads MEDIC_BUFFER_ENDPOINT and MEDIC_BUFFER_API_KEY from:
| config/social-config.php
|
| Keep the API key server-side only.
*/

require_once __DIR__ . '/../config/social-config.php';

/*
|--------------------------------------------------------------------------
| Basic configuration
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_buffer_endpoint')) {
    function medic_buffer_endpoint(): string
    {
        $endpoint = defined('MEDIC_BUFFER_ENDPOINT')
            ? trim((string) MEDIC_BUFFER_ENDPOINT)
            : '';

        return $endpoint !== '' ? $endpoint : 'https://api.buffer.com';
    }
}

if (!function_exists('medic_buffer_api_key')) {
    function medic_buffer_api_key(): string
    {
        $configured_key = defined('MEDIC_BUFFER_API_KEY')
            ? trim((string) MEDIC_BUFFER_API_KEY)
            : '';

        if ($configured_key !== '') {
            return $configured_key;
        }

        $environment_key = getenv('BUFFER_API_KEY');

        return is_string($environment_key) ? trim($environment_key) : '';
    }
}

if (!function_exists('medic_buffer_has_api_key')) {
    function medic_buffer_has_api_key(): bool
    {
        return medic_buffer_api_key() !== '';
    }
}

/*
|--------------------------------------------------------------------------
| Backward-compatible alias
|--------------------------------------------------------------------------
| Existing social-posts.php and doctor-social-post.php use this name.
*/
if (!function_exists('medic_buffer_is_configured')) {
    function medic_buffer_is_configured(): bool
    {
        return medic_buffer_has_api_key();
    }
}

if (!function_exists('medic_buffer_allowed_modes')) {
    function medic_buffer_allowed_modes(): array
    {
        return [
            'addToQueue',
            'shareNow',
            'shareNext',
            'customScheduled',
        ];
    }
}

if (!function_exists('medic_buffer_normalize_mode')) {
    function medic_buffer_normalize_mode(string $mode): string
    {
        $mode = trim($mode);

        return in_array($mode, medic_buffer_allowed_modes(), true)
            ? $mode
            : 'addToQueue';
    }
}

/*
|--------------------------------------------------------------------------
| GraphQL transport
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_buffer_graphql_errors')) {
    function medic_buffer_graphql_errors(array $payload): array
    {
        $messages = [];

        foreach ((array) ($payload['errors'] ?? []) as $error) {
            if (is_string($error) && trim($error) !== '') {
                $messages[] = trim($error);
                continue;
            }

            if (!is_array($error)) {
                continue;
            }

            $message = trim((string) ($error['message'] ?? ''));
            $code = trim((string) ($error['extensions']['code'] ?? ''));

            if ($message !== '' && $code !== '') {
                $messages[] = '[' . $code . '] ' . $message;
            } elseif ($message !== '') {
                $messages[] = $message;
            }
        }

        return array_values(array_unique($messages));
    }
}

if (!function_exists('medic_buffer_retryable_http_code')) {
    function medic_buffer_retryable_http_code(int $http_code): bool
    {
        return $http_code === 0
            || $http_code === 408
            || $http_code === 429
            || $http_code >= 500;
    }
}

if (!function_exists('medic_buffer_graphql')) {
    function medic_buffer_graphql(string $query, array $variables = []): array
    {
        $api_key = medic_buffer_api_key();

        if ($api_key === '') {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => [],
                'errors' => ['Buffer API key is not configured.'],
                'raw' => '',
                'retryable' => false,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => [],
                'errors' => ['PHP cURL extension is required for Buffer API requests.'],
                'raw' => '',
                'retryable' => false,
            ];
        }

        /*
         * Buffer requires GraphQL variables to be a JSON object when present.
         * PHP encodes an empty array as [], which Buffer rejects.
         * Queries without variables must omit the variables field entirely.
         */
        $request_payload = [
            'query' => $query,
        ];

        if (!empty($variables)) {
            $request_payload['variables'] = $variables;
        }

        $payload = json_encode(
            $request_payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($payload === false) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => [],
                'errors' => ['Could not encode the Buffer GraphQL request.'],
                'raw' => '',
                'retryable' => false,
            ];
        }

        $curl = curl_init(medic_buffer_endpoint());

        if ($curl === false) {
            return [
                'ok' => false,
                'http_code' => 0,
                'data' => [],
                'errors' => ['Could not initialize the Buffer API request.'],
                'raw' => '',
                'retryable' => true,
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'User-Agent: MedicBD-Buffer-Integration/1.1',
            ],
        ]);

        $raw = curl_exec($curl);
        $curl_error = trim((string) curl_error($curl));
        $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($raw === false) {
            return [
                'ok' => false,
                'http_code' => $http_code,
                'data' => [],
                'errors' => [
                    $curl_error !== ''
                        ? $curl_error
                        : 'Could not connect to Buffer API.',
                ],
                'raw' => '',
                'retryable' => medic_buffer_retryable_http_code($http_code),
            ];
        }

        $raw = (string) $raw;
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'http_code' => $http_code,
                'data' => [],
                'errors' => ['Buffer returned an invalid JSON response.'],
                'raw' => $raw,
                'retryable' => medic_buffer_retryable_http_code($http_code),
            ];
        }

        $errors = medic_buffer_graphql_errors($decoded);

        /*
         * GraphQL can return HTTP 200 even if "errors" exists in the response.
         */
        if ($http_code !== 0 && ($http_code < 200 || $http_code >= 300)) {
            $errors[] = 'Buffer returned HTTP status ' . $http_code . '.';
        }

        $errors = array_values(array_unique($errors));

        return [
            'ok' => empty($errors),
            'http_code' => $http_code,
            'data' => (array) ($decoded['data'] ?? []),
            'errors' => $errors,
            'raw' => $raw,
            'retryable' => !empty($errors) && medic_buffer_retryable_http_code($http_code),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Buffer account and channels
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_buffer_get_organizations')) {
    function medic_buffer_get_organizations(): array
    {
        /*
         * Keep this query to the documented fields only. A smaller query is
         * less likely to break when account-level fields change.
         */
        $query = <<<'GRAPHQL'
query GetOrganizations {
  account {
    organizations {
      id
      name
    }
  }
}
GRAPHQL;

        $response = medic_buffer_graphql($query);

        if (!$response['ok']) {
            return [
                'ok' => false,
                'account' => [],
                'organizations' => [],
                'errors' => $response['errors'],
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
            ];
        }

        $account = (array) ($response['data']['account'] ?? []);

        return [
            'ok' => true,
            'account' => $account,
            'organizations' => (array) ($account['organizations'] ?? []),
            'errors' => [],
            'raw' => $response['raw'],
            'http_code' => $response['http_code'],
        ];
    }
}

if (!function_exists('medic_buffer_get_channels')) {
    function medic_buffer_get_channels(string $organization_id): array
    {
        $organization_id = trim($organization_id);

        if ($organization_id === '') {
            return [
                'ok' => false,
                'channels' => [],
                'errors' => ['Choose a Buffer organization first.'],
                'raw' => '',
                'http_code' => 0,
            ];
        }

        $query = <<<'GRAPHQL'
query GetChannels($organizationId: OrganizationId!) {
  channels(input: { organizationId: $organizationId }) {
    id
    name
    displayName
    service
    avatar
    isQueuePaused
  }
}
GRAPHQL;

        $response = medic_buffer_graphql($query, [
            'organizationId' => $organization_id,
        ]);

        if (!$response['ok']) {
            return [
                'ok' => false,
                'channels' => [],
                'errors' => $response['errors'],
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
            ];
        }

        $channels = [];

        foreach ((array) ($response['data']['channels'] ?? []) as $channel) {
            if (!is_array($channel)) {
                continue;
            }

            /*
             * These defaults preserve compatibility with the existing admin UI.
             */
            $channel['isDisconnected'] = !empty($channel['isDisconnected']);
            $channel['isLocked'] = !empty($channel['isLocked']);
            $channel['descriptor'] = (string) ($channel['descriptor'] ?? '');

            $channels[] = $channel;
        }

        return [
            'ok' => true,
            'channels' => $channels,
            'errors' => [],
            'raw' => $response['raw'],
            'http_code' => $response['http_code'],
        ];
    }
}

if (!function_exists('medic_buffer_find_channel')) {
    function medic_buffer_find_channel(array $channels, string $channel_id): ?array
    {
        $channel_id = trim($channel_id);

        foreach ($channels as $channel) {
            if (
                is_array($channel)
                && trim((string) ($channel['id'] ?? '')) === $channel_id
            ) {
                return $channel;
            }
        }

        return null;
    }
}

if (!function_exists('medic_buffer_channel_ready')) {
    function medic_buffer_channel_ready(array $channel): array
    {
        if (trim((string) ($channel['id'] ?? '')) === '') {
            return [
                'ok' => false,
                'message' => 'The selected Buffer channel is invalid.',
            ];
        }

        if (!empty($channel['isDisconnected'])) {
            return [
                'ok' => false,
                'message' => 'The selected Buffer channel is disconnected in Buffer.',
            ];
        }

        if (!empty($channel['isLocked'])) {
            return [
                'ok' => false,
                'message' => 'The selected Buffer channel is locked in Buffer.',
            ];
        }

        return [
            'ok' => true,
            'message' => '',
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Buffer Ideas
|--------------------------------------------------------------------------
| Ideas are drafts within a Buffer organization. They do not publish directly
| to a Facebook, Instagram, LinkedIn, or X channel.
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_buffer_create_idea')) {
    function medic_buffer_create_idea(
        string $organization_id,
        string $title,
        string $text
    ): array {
        $organization_id = trim($organization_id);
        $title = trim($title);
        $text = trim($text);

        if ($organization_id === '') {
            return [
                'ok' => false,
                'idea' => [],
                'message' => 'A Buffer organization ID is required.',
                'raw' => '',
                'http_code' => 0,
                'retryable' => false,
            ];
        }

        if ($title === '' && $text === '') {
            return [
                'ok' => false,
                'idea' => [],
                'message' => 'Idea title or text is required.',
                'raw' => '',
                'http_code' => 0,
                'retryable' => false,
            ];
        }

        $query = <<<'GRAPHQL'
mutation CreateIdea($input: CreateIdeaInput!) {
  createIdea(input: $input) {
    __typename
    ... on Idea {
      id
      content {
        title
        text
      }
    }
    ... on MutationError {
      message
    }
  }
}
GRAPHQL;

        $response = medic_buffer_graphql($query, [
            'input' => [
                'organizationId' => $organization_id,
                'content' => [
                    'title' => $title,
                    'text' => $text,
                ],
            ],
        ]);

        if (!$response['ok']) {
            return [
                'ok' => false,
                'idea' => [],
                'message' => implode(' ', $response['errors']),
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
                'retryable' => $response['retryable'],
            ];
        }

        $result = (array) ($response['data']['createIdea'] ?? []);

        if (!empty($result['message'])) {
            return [
                'ok' => false,
                'idea' => [],
                'message' => trim((string) $result['message']),
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
                'retryable' => false,
            ];
        }

        if (empty($result['id'])) {
            return [
                'ok' => false,
                'idea' => [],
                'message' => 'Buffer did not return an idea ID.',
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
                'retryable' => false,
            ];
        }

        return [
            'ok' => true,
            'idea' => $result,
            'message' => '',
            'raw' => $response['raw'],
            'http_code' => $response['http_code'],
            'retryable' => false,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Post creation
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_buffer_public_image_url')) {
    function medic_buffer_public_image_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);

        if ($validated === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            ? $validated
            : '';
    }
}

if (!function_exists('medic_buffer_local_to_utc')) {
    function medic_buffer_local_to_utc(
        string $local_datetime,
        string $timezone = 'Asia/Dhaka'
    ): ?string {
        $local_datetime = trim($local_datetime);

        if ($local_datetime === '') {
            return null;
        }

        try {
            $source_timezone = new DateTimeZone($timezone);
            $utc_timezone = new DateTimeZone('UTC');

            $formats = [
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i',
                'Y-m-d H:i:s',
                'Y-m-d H:i',
            ];

            foreach ($formats as $format) {
                $date = DateTimeImmutable::createFromFormat(
                    '!' . $format,
                    $local_datetime,
                    $source_timezone
                );

                $errors = DateTimeImmutable::getLastErrors();

                $valid = $date instanceof DateTimeImmutable
                    && (
                        $errors === false
                        || (
                            (int) ($errors['warning_count'] ?? 0) === 0
                            && (int) ($errors['error_count'] ?? 0) === 0
                        )
                    );

                if ($valid) {
                    return $date
                        ->setTimezone($utc_timezone)
                        ->format('Y-m-d\TH:i:s.000\Z');
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }
}

if (!function_exists('medic_buffer_is_utc_datetime')) {
    function medic_buffer_is_utc_datetime(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || substr(strtoupper($value), -1) !== 'Z') {
            return false;
        }

        try {
            new DateTimeImmutable($value);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Platform-specific post metadata
|--------------------------------------------------------------------------
| Buffer requires an explicit post type for Facebook and Instagram.
| Standard doctor promotions are normal feed posts, not stories or reels.
*/
if (!function_exists('medic_buffer_metadata_for_platform')) {
    function medic_buffer_metadata_for_platform(string $platform): array
    {
        $platform = strtolower(trim($platform));

        if ($platform === 'facebook') {
            return [
                'facebook' => [
                    'type' => 'post',
                ],
            ];
        }

        if ($platform === 'instagram') {
            return [
                'instagram' => [
                    'type' => 'post',
                    'shouldShareToFeed' => true,
                ],
            ];
        }

        return [];
    }
}

if (!function_exists('medic_buffer_create_post')) {
    function medic_buffer_create_post(
        string $channel_id,
        string $text,
        string $mode = 'addToQueue',
        ?string $due_at_utc = null,
        string $image_url = '',
        array $metadata = [],
        string $source = 'medic-admin-social-post'
    ): array {
        $channel_id = trim($channel_id);
        $text = trim($text);
        $mode = medic_buffer_normalize_mode($mode);
        $image_url = medic_buffer_public_image_url($image_url);

        if ($channel_id === '') {
            return [
                'ok' => false,
                'post' => [],
                'message' => 'A Buffer channel ID is required.',
                'raw' => '',
                'http_code' => 0,
                'retryable' => false,
            ];
        }

        if ($text === '') {
            return [
                'ok' => false,
                'post' => [],
                'message' => 'Post text cannot be empty.',
                'raw' => '',
                'http_code' => 0,
                'retryable' => false,
            ];
        }

        if ($mode === 'customScheduled') {
            $due_at_utc = trim((string) $due_at_utc);

            if (!medic_buffer_is_utc_datetime($due_at_utc)) {
                return [
                    'ok' => false,
                    'post' => [],
                    'message' => 'Scheduled posts require an ISO 8601 UTC dueAt value.',
                    'raw' => '',
                    'http_code' => 0,
                    'retryable' => false,
                ];
            }
        }

        $input = [
            'channelId' => $channel_id,
            'text' => $text,
            'schedulingType' => 'automatic',
            'mode' => $mode,
            'assets' => [],
            'source' => trim($source) !== '' ? trim($source) : 'medic-admin-social-post',
        ];

        /*
         * Buffer needs a publicly reachable image URL. Instagram posts should
         * be sent with an image; the admin page validates that before calling.
         */
        if ($image_url !== '') {
            $input['assets'][] = [
                'image' => [
                    'url' => $image_url,
                ],
            ];
        }

        if ($mode === 'customScheduled') {
            $input['dueAt'] = $due_at_utc;
        }

        if (!empty($metadata)) {
            $input['metadata'] = $metadata;
        }

        $query = <<<'GRAPHQL'
mutation CreatePost($input: CreatePostInput!) {
  createPost(input: $input) {
    __typename
    ... on PostActionSuccess {
      post {
        id
        text
        dueAt
        status
        channelId
        assets {
          id
          mimeType
        }
      }
    }
    ... on MutationError {
      message
    }
  }
}
GRAPHQL;

        $response = medic_buffer_graphql($query, [
            'input' => $input,
        ]);

        if (!$response['ok']) {
            return [
                'ok' => false,
                'post' => [],
                'message' => implode(' ', $response['errors']),
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
                'retryable' => $response['retryable'],
            ];
        }

        $result = (array) ($response['data']['createPost'] ?? []);

        if (!empty($result['message'])) {
            return [
                'ok' => false,
                'post' => [],
                'message' => trim((string) $result['message']),
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
                'retryable' => false,
            ];
        }

        $post = (array) ($result['post'] ?? []);

        if (empty($post['id'])) {
            return [
                'ok' => false,
                'post' => [],
                'message' => 'Buffer did not return a post ID.',
                'raw' => $response['raw'],
                'http_code' => $response['http_code'],
                'retryable' => false,
            ];
        }

        return [
            'ok' => true,
            'post' => $post,
            'message' => '',
            'raw' => $response['raw'],
            'http_code' => $response['http_code'],
            'retryable' => false,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Multiple destination helper
|--------------------------------------------------------------------------
*/
if (!function_exists('medic_buffer_publish_targets')) {
    function medic_buffer_publish_targets(
        array $targets,
        string $mode = 'addToQueue',
        ?string $due_at_utc = null
    ): array {
        $results = [];
        $mode = medic_buffer_normalize_mode($mode);

        foreach ($targets as $platform => $target) {
            if (!is_array($target)) {
                $results[(string) $platform] = [
                    'ok' => false,
                    'post' => [],
                    'message' => 'Invalid target data.',
                    'raw' => '',
                    'http_code' => 0,
                    'retryable' => false,
                ];
                continue;
            }

            $results[(string) $platform] = medic_buffer_create_post(
                (string) ($target['channel_id'] ?? ''),
                (string) ($target['caption'] ?? ''),
                $mode,
                $due_at_utc,
                (string) ($target['image_url'] ?? ''),
                (array) ($target['metadata'] ?? []),
                'medic-doctor-social-post'
            );
        }

        return $results;
    }
}

if (!function_exists('medic_buffer_connection_test')) {
    function medic_buffer_connection_test(): array
    {
        $result = medic_buffer_get_organizations();

        return [
            'ok' => !empty($result['ok']),
            'message' => !empty($result['ok'])
                ? 'Buffer API connection is working.'
                : implode(' ', (array) ($result['errors'] ?? ['Buffer connection failed.'])),
            'organizations' => (array) ($result['organizations'] ?? []),
        ];
    }
}
