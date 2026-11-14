<?php

declare(strict_types=1);

namespace App\Support\Sentry;

use Sentry\Event;
use Sentry\EventHint;

/**
 * before_send-hook voor Sentry. De RequestIntegration van sentry-php neemt de
 * query string los van send_default_pii mee, in request.query_string, in
 * request.url en via de Referer-header. Een resetlink met ?email= zou zo een
 * e-mailadres naar Sentry sturen. Deze hook haalt de query string uit die
 * velden en gooit een eventueel meegestuurde body weg; max_request_body_size
 * staat al op never.
 */
final class ScrubRequestData
{
    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        $request = $event->getRequest();

        if ($request === []) {
            return $event;
        }

        unset($request['query_string'], $request['data']);

        if (isset($request['url']) && is_string($request['url'])) {
            $request['url'] = self::withoutQuery($request['url']);
        }

        if (isset($request['headers']) && is_array($request['headers'])) {
            foreach ($request['headers'] as $name => $values) {
                if (strtolower((string) $name) !== 'referer') {
                    continue;
                }

                $request['headers'][$name] = is_array($values)
                    ? array_map(fn (mixed $value): mixed => is_string($value) ? self::withoutQuery($value) : $value, $values)
                    : (is_string($values) ? self::withoutQuery($values) : $values);
            }
        }

        $event->setRequest($request);

        return $event;
    }

    private static function withoutQuery(string $url): string
    {
        return preg_replace('/[?#].*$/s', '', $url) ?? $url;
    }
}
