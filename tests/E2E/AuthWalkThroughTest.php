<?php

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverDimension;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

final class AuthWalkThroughTest extends PantherTestCase
{
    private const REG_FORM   = 'form.vstack.gap-2';
    private const LOGIN_FORM = 'form[name="login_form"]';
    private const EMAIL_INP  = 'input[placeholder="Email"][type="email"]';
    private const PASS_INP   = 'input[placeholder="Password"][type="password"]';
    private const SUBMIT_BTN = 'button.btn.btn-primary.w-100';
    private const LOGIN_EMAIL_ID = '#login_form__username';
    private const LOGIN_PASS_ID  = '#login_form__password';
    private const LOGIN_CSRF_ID  = '#login_form__csrf_token';
    private const ALERT_ERR = '.alert.alert-danger';
    private const ALERT_OK  = '.alert.alert-success';

    private function createClientWithBase(): Client
    {
        $base   = $_SERVER['PANTHER_EXTERNAL_BASE_URI'] ?? null;
        $client = static::createPantherClient(['browser' => PantherTestCase::FIREFOX], [], [], $base);

        if (($_SERVER['PANTHER_NO_HEADLESS'] ?? getenv('PANTHER_NO_HEADLESS')) === '1') {
            $client->manage()->window()->setSize(new WebDriverDimension(1280, 900));
        }
        return $client;
    }

    private function clearField(Client $client, string $css): void
    {
        try {
            $client->getCrawler()->filter($css)->clear();
        } catch (\Throwable) {
            $client->executeScript(
                'var el=document.querySelector(arguments[0]); if(el){ el.value=""; }',
                [$css]
            );
        }
    }

    private function forceAnonymous(Client $client): void
    {
        $client->request('GET', '/');
        $client->waitFor('body');

        if (str_contains($client->getPageSource(), 'href="/logout"')) {
            $client->request('GET', '/logout');
            $client->wait(10)->until(fn() => !str_contains($client->getPageSource(), 'href="/logout"'));
        }
        $client->manage()->deleteAllCookies();
        $client->refreshCrawler();
    }

    private function path(Client $client): string
    {
        return (string) $client->executeScript('return window.location.pathname;');
    }

    private static function artifactDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/var/panther';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function saveArtifacts(Client $client, string $baseName): void
    {
        $dir = self::artifactDir();
        $client->takeScreenshot($dir . "/{$baseName}.png");
        file_put_contents($dir . "/{$baseName}.html", $client->getPageSource());
    }

    #[Test]
    public function testUserCanRegister(): array
    {
        $client = $this->createClientWithBase();

        $email = 'e2e+' . uniqid('', true) . '@example.test';
        $pass  = 'Secret123!';

        $client->request('GET', '/register');
        $client->waitFor(self::REG_FORM);
        self::assertSelectorTextContains('h1.h3.mb-3', 'Register');
        self::assertSelectorExists(self::EMAIL_INP);
        self::assertSelectorExists(self::PASS_INP);
        self::assertSelectorExists(self::SUBMIT_BTN);

        $csrf = (string) $client->executeScript(
            'const el=document.querySelector("input[type=\'hidden\'][name$=\'[_token]\']"); return el?el.value:"";'
        );
        self::assertNotSame('', $csrf, 'Missing CSRF token on /register.');

        $this->clearField($client, self::EMAIL_INP);
        $this->clearField($client, self::PASS_INP);
        $client->getCrawler()->filter(self::EMAIL_INP)->sendKeys($email);
        $client->getCrawler()->filter(self::PASS_INP)->sendKeys($pass);
        $client->getCrawler()->filter(self::SUBMIT_BTN)->click();

        $client->wait(20)->until(function () use ($client) {
            return $this->path($client) !== '/register'
                || (bool) $client->executeScript('return !!document.querySelector(arguments[0])', [self::ALERT_OK]);
        });

        $crawler     = $client->refreshCrawler();
        $currentPath = $this->path($client);

        if ($currentPath === '/register') {
            self::assertGreaterThan(0, $crawler->filter(self::ALERT_OK)->count(), 'Expected success flash.');
            if ($crawler->filter('a[href="/login"]')->count() > 0) {
                $crawler->filter('a[href="/login"]')->click();
            } else {
                $client->request('GET', '/login');
            }
            $client->waitFor(self::LOGIN_FORM);
        } else {
            self::assertNotSame('/register', $currentPath, 'Should have left /register.');
        }

        return ['email' => $email, 'password' => $pass];
    }

    #[Test]
    #[Depends('testUserCanRegister')]
    public function testRegisteredUserCanLogin(array $creds): void
    {
        $client = $this->createClientWithBase();

        $client->request('GET', '/login');
        $client->waitFor(self::LOGIN_FORM);
        self::assertSelectorTextContains('h1.h3.mb-3', 'Login');
        self::assertSelectorExists(self::LOGIN_EMAIL_ID . '[type="email"].form-control');
        self::assertSelectorExists(self::LOGIN_PASS_ID . '[type="password"].form-control');
        self::assertSelectorExists('input' . self::LOGIN_CSRF_ID . '[type="hidden"]');

        $csrf = (string) $client->executeScript(
            'const el=document.querySelector(arguments[0]); return el?el.value:"";',
            [self::LOGIN_CSRF_ID]
        );
        self::assertNotSame('', $csrf, 'Missing login CSRF token.');

        $this->clearField($client, self::LOGIN_EMAIL_ID);
        $this->clearField($client, self::LOGIN_PASS_ID);
        $client->getCrawler()->filter(self::LOGIN_EMAIL_ID)->sendKeys($creds['email']);
        $client->getCrawler()->filter(self::LOGIN_PASS_ID)->sendKeys($creds['password']);
        $client->getCrawler()->filter(self::SUBMIT_BTN)->click();

        $client->wait(20)->until(function () use ($client) {
            return $this->path($client) !== '/login'
                || (bool) $client->executeScript('return !!document.querySelector(arguments[0])', [self::ALERT_ERR]);
        });

        $crawler = $client->refreshCrawler();
        if ($crawler->filter(self::ALERT_ERR)->count() > 0) {
            $this->saveArtifacts($client, 'login-failed');
            self::fail('Login failed (see var/panther/login-failed.*).');
        }

        self::assertNotSame('/login', $this->path($client), 'Expected redirect after login.');
        self::assertStringNotContainsString('href="/login"', $client->getPageSource(), 'Login link should be gone.');

        $cookieNames = array_map(fn($c) => $c->getName(), $client->manage()->getCookies());
        $hasSession  = (bool) array_filter($cookieNames, fn($n) => preg_match('/(PHPSESSID|MOCKSESSID)/i', $n));
        self::assertTrue($hasSession, 'Expected a session cookie after login.');
    }

    #[Test]
    #[Depends('testUserCanRegister')]
    public function testLoginFailsWithInvalidCredentials(array $creds): void
    {
        $client = $this->createClientWithBase();
        $this->forceAnonymous($client);

        $client->request('GET', '/login');
        $client->waitFor(self::LOGIN_FORM);
        self::assertSelectorTextContains('h1.h3.mb-3', 'Login');

        $this->clearField($client, self::LOGIN_EMAIL_ID);
        $this->clearField($client, self::LOGIN_PASS_ID);
        $client->getCrawler()->filter(self::LOGIN_EMAIL_ID)->sendKeys($creds['email']);
        $client->getCrawler()->filter(self::LOGIN_PASS_ID)->sendKeys($creds['password'] . '-wrong');
        $client->getCrawler()->filter(self::SUBMIT_BTN)->click();

        $client->wait(15)->until(function () use ($client) {
            return $this->path($client) === '/login'
                && (bool) $client->executeScript('return !!document.querySelector(arguments[0])', [self::ALERT_ERR]);
        });

        $crawler = $client->refreshCrawler();
        self::assertSame('/login', $this->path($client), 'Should remain on /login after invalid credentials.');
        self::assertGreaterThan(0, $crawler->filter(self::ALERT_ERR)->count(), 'Expected an error alert.');

        $html = $client->getPageSource();
        self::assertStringNotContainsString('href="/logout"', $html, 'Logout should not be visible for anonymous user.');
        self::assertStringContainsString('href="/login"', $html, 'Login link should be visible for anonymous user.');

        if ($crawler->filter(self::ALERT_ERR)->count() === 0) {
            $this->saveArtifacts($client, 'login-invalid-creds');
        }
    }
}
