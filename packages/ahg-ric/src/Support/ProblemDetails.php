<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * RFC 7807 Problem Details helper for the /api/ric/v1 surface. Resolves
 * OpenRiC spec Q6: every error response from a spec-governed endpoint
 * emits `application/problem+json` with the canonical five base fields
 * (type, title, status, detail, instance) plus any extra context the
 * caller supplies.
 *
 * Error type URIs live under https://openric.org/errors/ and are the
 * stable identifiers a client can dispatch on — titles and details may
 * be localised or reworded without breaking clients.
 */

namespace AhgRic\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProblemDetails
{
    public const TYPE_BASE = 'https://openric.org/errors/';

    public const NOT_FOUND               = self::TYPE_BASE . 'not-found';
    public const BAD_REQUEST             = self::TYPE_BASE . 'bad-request';
    public const VALIDATION_FAILED       = self::TYPE_BASE . 'validation-failed';
    public const AUTHENTICATION_REQUIRED = self::TYPE_BASE . 'authentication-required';
    public const FORBIDDEN               = self::TYPE_BASE . 'forbidden';
    public const CONFLICT                = self::TYPE_BASE . 'conflict';
    public const PAYLOAD_TOO_LARGE       = self::TYPE_BASE . 'payload-too-large';
    public const UNSUPPORTED_MEDIA_TYPE  = self::TYPE_BASE . 'unsupported-media-type';
    public const INTERNAL_ERROR          = self::TYPE_BASE . 'internal-error';

    public static function notFound(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::NOT_FOUND, 'Not Found', 404, $detail, $extra);
    }

    public static function badRequest(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::BAD_REQUEST, 'Bad Request', 400, $detail, $extra);
    }

    public static function validationFailed(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::VALIDATION_FAILED, 'Validation Failed', 422, $detail, $extra);
    }

    public static function authenticationRequired(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::AUTHENTICATION_REQUIRED, 'Authentication Required', 401, $detail, $extra);
    }

    public static function forbidden(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::FORBIDDEN, 'Forbidden', 403, $detail, $extra);
    }

    public static function conflict(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::CONFLICT, 'Conflict', 409, $detail, $extra);
    }

    public static function payloadTooLarge(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::PAYLOAD_TOO_LARGE, 'Payload Too Large', 413, $detail, $extra);
    }

    public static function unsupportedMediaType(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::UNSUPPORTED_MEDIA_TYPE, 'Unsupported Media Type', 415, $detail, $extra);
    }

    public static function internalError(string $detail, array $extra = []): JsonResponse
    {
        return self::build(self::INTERNAL_ERROR, 'Internal Server Error', 500, $detail, $extra);
    }

    private static function build(string $type, string $title, int $status, string $detail, array $extra): JsonResponse
    {
        $body = array_merge([
            'type'     => $type,
            'title'    => $title,
            'status'   => $status,
            'detail'   => $detail,
            'instance' => self::instance(),
        ], $extra);

        return new JsonResponse($body, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }

    private static function instance(): string
    {
        $req = request();
        if ($req instanceof Request) {
            $path = $req->getPathInfo();
            $query = $req->getQueryString();
            return $query ? $path . '?' . $query : $path;
        }
        return '';
    }
}
