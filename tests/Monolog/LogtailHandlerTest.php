<?php

namespace Logtail\Monolog;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;

class MockLogtailClient extends LogtailClient {
    public $capturedData = NULL;

    public function __construct()
    {
        parent::__construct("test-source-token");
    }

    public function send($data): void
    {
        $this->capturedData = $data;
    }
}

class FailingLogtailClient extends LogtailClient
{
    public function __construct()
    {
        parent::__construct('test-source-token');
    }

    public function send($data): void
    {
        throw new \RuntimeException('simulated transport failure');
    }
}

class LogtailHandlerTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void
    {
        // set global $_SERVER data
        global $_SERVER;
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_URI' => '',
            'REMOTE_ADDR' => '',
            'REQUEST_METHOD' => '',
            'SERVER_NAME' => '',
            'HTTP_REFERER' => '',
        ]);
    }


    public function testHandlerWrite() {
        $handler = new \Logtail\Monolog\SynchronousLogtailHandler('sourceTokenXYZ');

        // hack: replace the private client object
        $mockClient = new MockLogtailClient;
        $setMockClient = function() use ($mockClient) {
            $this->client = $mockClient;
        };
        $setMockClient->call($handler);

        $logger = new \Monolog\Logger('test');
        $logger->pushHandler($handler);
        $logger->debug('test message');

        $decoded = \json_decode($mockClient->capturedData, true);

        $this->assertArrayHasKey('monolog', $decoded);
        $this->assertArrayHasKey('extra', $decoded['monolog']);

        // the introspection processor
        $this->assertArrayHasKey('file', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('line', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('class', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('function', $decoded['monolog']['extra']);

        // the web processor
        $this->assertArrayHasKey('url', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('ip', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('http_method', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('server', $decoded['monolog']['extra']);
        $this->assertArrayHasKey('referrer', $decoded['monolog']['extra']);

        // the process ID processor
        $this->assertArrayHasKey('process_id', $decoded['monolog']['extra']);

        // the hostname processor
        $this->assertArrayHasKey('hostname', $decoded['monolog']['extra']);
    }


    public function testHandlerWriteWithLineFormatter() {
        $handler = new \Logtail\Monolog\SynchronousLogtailHandler('sourceTokenXYZ');

        // test a scenario when the formatter has been set, so the default formatter is not used
        // this is the case with e.g. Laravel
        $handler->setFormatter(new LineFormatter());

        // hack: replace the private client object
        $mockClient = new MockLogtailClient;
        $setMockClient = function() use ($mockClient) {
            $this->client = $mockClient;
        };
        $setMockClient->call($handler);

        $logger = new \Monolog\Logger('test');
        $logger->pushHandler($handler);
        $logger->debug('test message');

        $decoded = \json_decode($mockClient->capturedData, true);

        $this->assertEquals(0, json_last_error(), "The formatted data is not valid JSON");
    }

    public function testHandlerWriteWithBatchWrite() {
        $synchronousHandler = new \Logtail\Monolog\SynchronousLogtailHandler('sourceTokenXYZ');
        $handler = new LogtailHandler('sourceTokenXYZ');

        // hack: replace the private client object
        $mockClient = new MockLogtailClient;
        $setMockClient = function() use ($mockClient) {
            $this->client = $mockClient;
        };
        $setMockHandler = function() use ($synchronousHandler) {
            $this->handler = $synchronousHandler;
        };

        $setMockClient->call($synchronousHandler);
        $setMockHandler->call($handler);



        $logger = new \Monolog\Logger('test');
        $logger->pushHandler($handler);
        $logger->debug('test message');
        $logger->debug('test message2');
        $handler->flush();

        $decoded = \json_decode($mockClient->capturedData, true);

        $this->assertEquals(0, json_last_error(), "The formatted data is not valid JSON");
        $this->assertTrue(is_array($decoded), "Expected array of logs");
        $this->assertCount(2, $decoded, "Expected two logs");
    }

    public function testBufferedResetDoesNotExposeTransportFailureWhenExceptionThrowingIsDisabled(): void
    {
        $synchronousHandler = new SynchronousLogtailHandler('sourceTokenXYZ');
        $handler = new LogtailHandler('sourceTokenXYZ');
        $failingClient = new FailingLogtailClient();

        (function() use ($failingClient): void {
            $this->client = $failingClient;
        })->call($synchronousHandler);
        (function() use ($synchronousHandler): void {
            $this->handler = $synchronousHandler;
        })->call($handler);

        set_error_handler(static function(int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $logger = new \Monolog\Logger('test');
            $logger->pushHandler($handler);
            $logger->error('test message');
            $logger->reset();

            $this->addToAssertionCount(1);
        } finally {
            restore_error_handler();
        }
    }

    public function testSynchronousWriteDoesNotExposeTransportFailureWhenExceptionThrowingIsDisabled(): void
    {
        $handler = new SynchronousLogtailHandler('sourceTokenXYZ');
        $failingClient = new FailingLogtailClient();

        (function() use ($failingClient): void {
            $this->client = $failingClient;
        })->call($handler);

        set_error_handler(static function(int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            $logger = new \Monolog\Logger('test');
            $logger->pushHandler($handler);
            $logger->error('test message');

            $this->addToAssertionCount(1);
        } finally {
            restore_error_handler();
        }
    }

    public function testBuilderPropagatesTransportFailureWhenExceptionThrowingIsEnabled(): void
    {
        $handler = LogtailHandlerBuilder::withSourceToken('sourceTokenXYZ')
            ->withExceptionThrowing(true)
            ->build();
        $synchronousHandler = (function(): SynchronousLogtailHandler {
            return $this->handler;
        })->call($handler);
        $failingClient = new FailingLogtailClient();

        (function() use ($failingClient): void {
            $this->client = $failingClient;
        })->call($synchronousHandler);

        $logger = new \Monolog\Logger('test');
        $logger->pushHandler($handler);
        $logger->error('test message');

        $exception = null;

        try {
            $logger->reset();
        } catch (\RuntimeException $throwable) {
            $exception = $throwable;
        } finally {
            $handler->clear();
        }

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('simulated transport failure', $exception->getMessage());
    }
}
