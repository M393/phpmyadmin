<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests;

use PhpMyAdmin\Bookmarks\BookmarkRepository;
use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\Console;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Header;
use PhpMyAdmin\Template;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;

use function gmdate;

use const DATE_RFC1123;

#[CoversClass(Header::class)]
#[Group('medium')]
class HeaderTest extends AbstractTestCase
{
    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        parent::setLanguage();

        DatabaseInterface::$instance = $this->createDatabaseInterface();

        $GLOBALS['message'] = 'phpmyadminmessage';
        Current::$database = 'db';
        Current::$table = '';

        parent::setGlobalConfig();

        $config = Config::getInstance();
        $config->settings['Servers'] = [];
        $config->selectedServer['DisableIS'] = false;
        $config->selectedServer['verbose'] = 'verbose host';
        $config->selectedServer['pmadb'] = '';
        $config->selectedServer['user'] = '';
        $config->selectedServer['auth_type'] = 'cookie';
    }

    private function getNewHeaderInstance(): Header
    {
        $dbi = DatabaseInterface::getInstance();
        $relation = new Relation($dbi);
        $template = new Template();

        return new Header(
            $template,
            new Console($relation, $template, new BookmarkRepository($dbi, $relation)),
            Config::getInstance(),
        );
    }

    /**
     * Test for disable
     */
    public function testDisable(): void
    {
        $header = $this->getNewHeaderInstance();
        $header->disable();
        self::assertEquals(
            '',
            $header->getDisplay(),
        );
    }

    /**
     * Test for enable
     */
    public function testEnable(): void
    {
        Current::$server = 0;
        $header = $this->getNewHeaderInstance();
        self::assertStringContainsString(
            '<title>phpMyAdmin</title>',
            $header->getDisplay(),
        );
    }

    /**
     * Test for Set BodyId
     */
    public function testSetBodyId(): void
    {
        $header = $this->getNewHeaderInstance();
        $header->setBodyId('PMA_header_id');
        self::assertStringContainsString(
            'PMA_header_id',
            $header->getDisplay(),
        );
    }

    /**
     * Test for Get JsParams
     */
    public function testGetJsParams(): void
    {
        $header = $this->getNewHeaderInstance();
        self::assertArrayHasKey(
            'common_query',
            $header->getJsParams(),
        );
    }

    public function testGetJsParamsCode(): void
    {
        $header = $this->getNewHeaderInstance();
        self::assertStringContainsString(
            'window.Navigation.update(window.CommonParams.setAll(',
            $header->getJsParamsCode(),
        );
    }

    /**
     * Test for Get Message
     */
    public function testGetMessage(): void
    {
        $header = $this->getNewHeaderInstance();
        self::assertStringContainsString(
            'phpmyadminmessage',
            $header->getMessage(),
        );
    }

    /**
     * Test for Disable Warnings
     */
    public function testDisableWarnings(): void
    {
        $reflection = new ReflectionProperty(Header::class, 'warningsEnabled');

        $header = $this->getNewHeaderInstance();
        $header->disableWarnings();

        self::assertFalse($reflection->getValue($header));
    }

    #[DataProvider('providerForTestGetHttpHeaders')]
    public function testGetHttpHeaders(
        string|bool $frameOptions,
        string $cspAllow,
        string $privateKey,
        string $publicKey,
        string $captchaCsp,
        string|null $expectedFrameOptions,
        string $expectedCsp,
        string $expectedXCsp,
        string $expectedWebKitCsp,
    ): void {
        $header = $this->getNewHeaderInstance();
        $date = gmdate(DATE_RFC1123);

        $config = Config::getInstance();
        $config->settings['AllowThirdPartyFraming'] = $frameOptions;
        $config->settings['CSPAllow'] = $cspAllow;
        $config->settings['CaptchaLoginPrivateKey'] = $privateKey;
        $config->settings['CaptchaLoginPublicKey'] = $publicKey;
        $config->settings['CaptchaCsp'] = $captchaCsp;

        $expected = [
            'X-Frame-Options' => $expectedFrameOptions,
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => $expectedCsp,
            'X-Content-Security-Policy' => $expectedXCsp,
            'X-WebKit-CSP' => $expectedWebKitCsp,
            'X-XSS-Protection' => '1; mode=block',
            'X-Content-Type-Options' => 'nosniff',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Permissions-Policy' => 'fullscreen=(self), oversized-images=(self), interest-cohort=()',
            'Expires' => $date,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, pre-check=0, post-check=0, max-age=0',
            'Pragma' => 'no-cache',
            'Last-Modified' => $date,
            'Content-Type' => 'text/html; charset=utf-8',
        ];
        if ($expectedFrameOptions === null) {
            unset($expected['X-Frame-Options']);
        }

        $headers = $this->callFunction($header, Header::class, 'getHttpHeaders', []);
        self::assertSame($expected, $headers);
    }

    /** @return mixed[][] */
    public static function providerForTestGetHttpHeaders(): array
    {
        return [
            [
                '1',
                '',
                '',
                '',
                '',
                'DENY',
                'default-src \'self\' ;script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' ;'
                    . 'style-src \'self\' \'unsafe-inline\' ;img-src \'self\' data:  tile.openstreetmap.org;'
                    . 'object-src \'none\';',
                'default-src \'self\' ;options inline-script eval-script;referrer no-referrer;'
                    . 'img-src \'self\' data:  tile.openstreetmap.org;object-src \'none\';',
                'default-src \'self\' ;script-src \'self\'  \'unsafe-inline\' \'unsafe-eval\';'
                    . 'referrer no-referrer;style-src \'self\' \'unsafe-inline\' ;'
                    . 'img-src \'self\' data:  tile.openstreetmap.org;object-src \'none\';',
            ],
            [
                'SameOrigin',
                'example.com example.net',
                'PrivateKey',
                'PublicKey',
                'captcha.tld csp.tld',
                'SAMEORIGIN',
                'default-src \'self\'  captcha.tld csp.tld example.com example.net;'
                    . 'script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'  '
                    . 'captcha.tld csp.tld example.com example.net;'
                    . 'style-src \'self\' \'unsafe-inline\'  captcha.tld csp.tld example.com example.net;'
                    . 'img-src \'self\' data: example.com example.net tile.openstreetmap.org captcha.tld csp.tld ;'
                    . 'object-src \'none\';',
                'default-src \'self\'  captcha.tld csp.tld example.com example.net;'
                    . 'options inline-script eval-script;referrer no-referrer;img-src \'self\' data: example.com '
                    . 'example.net tile.openstreetmap.org captcha.tld csp.tld ;object-src \'none\';',
                'default-src \'self\'  captcha.tld csp.tld example.com example.net;script-src \'self\'  '
                    . 'captcha.tld csp.tld example.com example.net \'unsafe-inline\' \'unsafe-eval\';'
                    . 'referrer no-referrer;style-src \'self\' \'unsafe-inline\'  captcha.tld csp.tld ;'
                    . 'img-src \'self\' data: example.com example.net tile.openstreetmap.org captcha.tld csp.tld ;'
                    . 'object-src \'none\';',
            ],
            [
                true,
                '',
                'PrivateKey',
                'PublicKey',
                'captcha.tld csp.tld',
                null,
                'default-src \'self\'  captcha.tld csp.tld ;'
                    . 'script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'  captcha.tld csp.tld ;'
                    . 'style-src \'self\' \'unsafe-inline\'  captcha.tld csp.tld ;'
                    . 'img-src \'self\' data:  tile.openstreetmap.org captcha.tld csp.tld ;object-src \'none\';',
                'default-src \'self\'  captcha.tld csp.tld ;'
                    . 'options inline-script eval-script;referrer no-referrer;'
                    . 'img-src \'self\' data:  tile.openstreetmap.org captcha.tld csp.tld ;object-src \'none\';',
                'default-src \'self\'  captcha.tld csp.tld ;'
                    . 'script-src \'self\'  captcha.tld csp.tld  \'unsafe-inline\' \'unsafe-eval\';'
                    . 'referrer no-referrer;style-src \'self\' \'unsafe-inline\'  captcha.tld csp.tld ;'
                    . 'img-src \'self\' data:  tile.openstreetmap.org captcha.tld csp.tld ;object-src \'none\';',
            ],
        ];
    }

    public function testAddedDefaultScripts(): void
    {
        $header = $this->getNewHeaderInstance();
        $scripts = $header->getScripts();
        $expected = [
            ['name' => 'runtime.js', 'fire' => 0],
            ['name' => 'vendor/jquery/jquery.min.js', 'fire' => 0],
            ['name' => 'vendor/jquery/jquery-migrate.min.js', 'fire' => 0],
            ['name' => 'vendor/sprintf.js', 'fire' => 0],
            ['name' => 'vendor/jquery/jquery-ui.min.js', 'fire' => 0],
            ['name' => 'name-conflict-fixes.js', 'fire' => 0],
            ['name' => 'vendor/bootstrap/bootstrap.bundle.min.js', 'fire' => 0],
            ['name' => 'vendor/js.cookie.min.js', 'fire' => 0],
            ['name' => 'vendor/jquery/jquery.validate.min.js', 'fire' => 0],
            ['name' => 'vendor/jquery/jquery-ui-timepicker-addon.js', 'fire' => 0],
            ['name' => 'index.php', 'fire' => 0],
            ['name' => 'shared.js', 'fire' => 0],
            ['name' => 'menu_resizer.js', 'fire' => 1],
            ['name' => 'main.js', 'fire' => 1],
        ];
        self::assertSame($expected, $scripts->getFiles());
    }

    public function testSetAjax(): void
    {
        $header = $this->getNewHeaderInstance();
        $console = (new ReflectionProperty(Header::class, 'console'))->getValue($header);
        self::assertInstanceOf(Console::class, $console);
        $isAjax = new ReflectionProperty(Header::class, 'isAjax');
        $consoleIsAjax = new ReflectionProperty(Console::class, 'isAjax');

        self::assertFalse($isAjax->getValue($header));
        self::assertFalse($consoleIsAjax->getValue($console));
        $header->setAjax(true);
        self::assertTrue($isAjax->getValue($header));
        self::assertTrue($consoleIsAjax->getValue($console));
        $header->setAjax(false);
        self::assertFalse($isAjax->getValue($header));
        self::assertFalse($consoleIsAjax->getValue($console));
    }
}
