<?php

namespace App\Tests\Utility;

use App\Entity\Message;
use App\Utility\Utils;
use PHPUnit\Framework\Attributes\{CoversClass, Group};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, Response};

#[Group('utility')]
#[CoversClass(Utils::class)]
class UtilsTest extends TestCase
{
    public function testApiResponseNullBodyAddsCorsAndJsonContentType(): void
    {
        $r = Utils::apiResponse(Response::HTTP_NO_CONTENT, null, 'json');

        self::assertSame(Response::HTTP_NO_CONTENT, $r->getStatusCode());
        self::assertSame('*', $r->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $r->headers->get('Access-Control-Allow-Credentials'));
        self::assertSame('application/json', $r->headers->get('Content-Type'));
        self::assertSame('', (string) $r->getContent());
    }

    public function testApiResponseSerializesObjectAsJsonAndAddsCustomHeaders(): void
    {
        $body = new Message(200, 'OK');

        $r = Utils::apiResponse(Response::HTTP_OK, $body, 'json', ['X-Test' => '1']);

        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertSame('1', $r->headers->get('X-Test'));
        self::assertSame('application/json', $r->headers->get('Content-Type'));
        self::assertNotSame('', (string) $r->getContent());
        self::assertStringContainsString('OK', (string) $r->getContent());
    }

    public function testApiResponseXmlContentType(): void
    {
        $body = new Message(200, 'OK');

        $r = Utils::apiResponse(Response::HTTP_OK, $body, 'xml');

        self::assertSame(Response::HTTP_OK, $r->getStatusCode());
        self::assertSame('application/xml', $r->headers->get('Content-Type'));
        self::assertNotSame('', (string) $r->getContent());
        self::assertStringContainsString('OK', (string) $r->getContent());
    }

    public function testGetFormatFromRequestAttributeOverridesAcceptHeader(): void
    {
        $request = Request::create('/any', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/xml',
        ]);
        $request->attributes->set('format', 'json');

        self::assertSame('json', Utils::getFormat($request));
    }

    public function testGetFormatFromAcceptHeaderXml(): void
    {
        $request = Request::create('/any', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/xml',
        ]);

        self::assertSame('xml', Utils::getFormat($request));
    }

    public function testGetFormatDefaultsToJson(): void
    {
        $request = Request::create('/any', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertSame('json', Utils::getFormat($request));
    }

    public function testErrorMessageUsesCustomMessageAndFormat(): void
    {
        $r = Utils::errorMessage(Response::HTTP_BAD_REQUEST, 'custom', 'json');

        self::assertSame(Response::HTTP_BAD_REQUEST, $r->getStatusCode());
        self::assertSame('application/json', $r->headers->get('Content-Type'));
        self::assertNotSame('', (string) $r->getContent());
        self::assertStringContainsString('custom', (string) $r->getContent());
    }

    public function testErrorMessageUsesDefaultStatusTextUppercaseWhenNullMessage(): void
    {
        $r = Utils::errorMessage(Response::HTTP_UNAUTHORIZED, null, 'json');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $r->getStatusCode());
        self::assertNotSame('', (string) $r->getContent());
        self::assertStringContainsString('UNAUTHORIZED', (string) $r->getContent());
    }
}
