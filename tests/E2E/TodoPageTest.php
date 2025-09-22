<?php

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverDimension;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

final class TodoPageTest extends PantherTestCase
{
    private const FORM_WRAPPER  = 'form.row.g-2';
    private const TASK_INPUT    = 'input[placeholder="Enter your task"]';
    private const ADD_BTN       = 'button.btn.btn-primary.w-100';
    private const ROW           = '.d-flex.justify-content-between.align-items-center.py-2.border-bottom';
    private const FLASH_SUCCESS = '.alert.alert-success';

    private function client(): Client
    {
        $base = $_SERVER['PANTHER_EXTERNAL_BASE_URI'] ?? null;

        $client = static::createPantherClient([
            'browser'      => self::FIREFOX,
            'capabilities' => ['moz:firefoxOptions' => ['args' => []]],
        ], [], [], $base);

        if (($_SERVER['PANTHER_NO_HEADLESS'] ?? getenv('PANTHER_NO_HEADLESS')) === '1') {
            $client->manage()->window()->setSize(new WebDriverDimension(1280, 900));
        }
        return $client;
    }

    private function js(Client $c, string $script, array $args = [])
    {
        return $c->executeScript($script, $args);
    }

    private function path(Client $c): string
    {
        return (string) $this->js($c, 'return window.location.pathname;');
    }

    private function clear(Client $c, string $css): void
    {
        try { $c->getCrawler()->filter($css)->clear(); }
        catch (\Throwable) { $this->js($c, 'const s=arguments[0]; const el=document.querySelector(s); if(el) el.value="";', [$css]); }
    }

    private function waitForSelector(Client $c, string $css, int $timeout = 10): void
    {
        $c->wait($timeout)->until(fn() => (bool) $this->js($c, 'return !!document.querySelector(arguments[0]);', [$css]));
    }

    private function forceAnonymous(Client $c): void
    {
        $c->request('GET', '/');
        $this->waitForSelector($c, 'body');
        if (str_contains($c->getPageSource(), 'href="/logout"')) {
            $c->request('GET', '/logout');
            $c->wait(10)->until(fn() => !str_contains($c->getPageSource(), 'href="/logout"'));
        }
        $c->manage()->deleteAllCookies();
        $c->refreshCrawler();
    }

    private function registerAndLogin(Client $c): array
    {
        $email = 'e2e+'.uniqid('', true).'@example.test';
        $pass  = 'Secret123!';

        $c->request('GET', '/register');
        $this->waitForSelector($c, 'form.vstack.gap-2');
        $this->clear($c, 'input[placeholder="Email"][type="email"]');
        $this->clear($c, 'input[placeholder="Password"][type="password"]');
        $c->getCrawler()->filter('input[placeholder="Email"]')->sendKeys($email);
        $c->getCrawler()->filter('input[placeholder="Password"]')->sendKeys($pass);
        $c->getCrawler()->filter(self::ADD_BTN)->click();

        $c->wait(20)->until(fn() => $this->path($c) !== '/register'
            || (bool) $this->js($c, 'return !!document.querySelector(".alert.alert-success");'));
        $c->refreshCrawler();

        if ($this->path($c) === '/register') {
            $this->js($c, 'const a=document.querySelector(\'a[href="/login"]\'); if(a){a.click();} else {window.location="/login";}');
            $this->waitForSelector($c, 'form[name="login_form"]');
        }

        $c->request('GET', '/login');
        $this->waitForSelector($c, 'form[name="login_form"]');
        $this->clear($c, '#login_form__username');
        $this->clear($c, '#login_form__password');
        $c->getCrawler()->filter('#login_form__username')->sendKeys($email);
        $c->getCrawler()->filter('#login_form__password')->sendKeys($pass);
        $c->getCrawler()->filter(self::ADD_BTN)->click();
        $c->wait(20)->until(fn() => $this->path($c) !== '/login');
        $c->refreshCrawler();

        return compact('email', 'pass');
    }

    private function fillDueAt(Client $c, \DateTimeImmutable $dt): void
    {
        $valDT   = $dt->format('Y-m-d\TH:i');
        $valDate = $dt->format('Y-m-d');
        $valTime = $dt->format('H:i');

        $found = $this->js($c, <<<'JS'
            const [valDT, valDate, valTime] = arguments;

            let el = document.querySelector('input[type="datetime-local"][name$="[dueAt]"], input[type="datetime-local"][id*="dueAt"]');
            if (el) { el.value=valDT; el.dispatchEvent(new Event('input',{bubbles:true})); el.dispatchEvent(new Event('change',{bubbles:true})); return 'single'; }

            let d = document.querySelector('input[type="date"][name*="[dueAt]"], input[type="date"][id*="dueAt"]');
            let t = document.querySelector('input[type="time"][name*="[dueAt]"], input[type="time"][id*="dueAt"]');
            if (d && t) {
                d.value=valDate; d.dispatchEvent(new Event('input',{bubbles:true})); d.dispatchEvent(new Event('change',{bubbles:true}));
                t.value=valTime; t.dispatchEvent(new Event('input',{bubbles:true})); t.dispatchEvent(new Event('change',{bubbles:true}));
                return 'split';
            }
            return null;
        JS, [$valDT, $valDate, $valTime]);

        self::assertNotNull($found, 'Could not locate due date field(s).');
    }


    #[Test]
    public function testIndexRequiresAuthentication(): void
    {
        $c = $this->client();
        $this->forceAnonymous($c);

        $c->request('GET', '/');
        $c->wait(10)->until(fn() => $this->path($c) === '/login');
        self::assertSame('/login', $this->path($c));
        self::assertStringContainsString('Login', $c->getPageSource());
    }

    #[Test]
    public function testAddToggleDeleteTodo_WithDueDate(): void
    {
        $c = $this->client();
        $this->registerAndLogin($c);

        $c->request('GET', '/');
        $this->waitForSelector($c, self::FORM_WRAPPER);
        self::assertSelectorExists(self::TASK_INPUT);
        self::assertSelectorTextContains('h1.card-title', 'My Todo List');

        $task = 'Buy milk ' . uniqid();
        $this->clear($c, self::TASK_INPUT);
        $c->getCrawler()->filter(self::TASK_INPUT)->sendKeys($task);
        $this->fillDueAt($c, new \DateTimeImmutable('+2 hours'));
        $c->getCrawler()->filter(self::ADD_BTN)->click();

        $c->wait(15)->until(fn() => str_contains($c->getPageSource(), $task)
            || (bool) $this->js($c, 'return !!document.querySelector(arguments[0]);', [self::FLASH_SUCCESS]));
        $c->refreshCrawler();
        self::assertStringContainsString($task, $c->getPageSource(), 'New todo should be listed.');

        $hasBadge = (bool) $this->js($c, <<<'JS'
            const task = arguments[0];
            const rows = [...document.querySelectorAll('.d-flex.justify-content-between.align-items-center.py-2.border-bottom')];
            const row  = rows.find(r => r.textContent.includes(task));
            if (!row) return false;
            const badge = row.querySelector('span.badge.rounded-pill');
            return !!(badge && badge.getAttribute('title'));
        JS, [$task]);
        self::assertTrue($hasBadge, 'Expected a due date badge.');

        $todoId = $this->js($c, <<<'JS'
            const task = arguments[0];
            const rows = [...document.querySelectorAll('.d-flex.justify-content-between.align-items-center.py-2.border-bottom')];
            const row  = rows.find(r => r.textContent.includes(task));
            if (!row) return null;
            const toggle = row.querySelector('form[action*="/todo/"][action*="/toggle"]');
            if (!toggle) return null;
            const m = toggle.action.match(/\/todo\/(\d+)\/toggle/);
            return m ? m[1] : null;
        JS, [$task]);
        self::assertNotNull($todoId, 'Could not infer todo ID.');

        $this->waitForSelector($c, sprintf('form[action*="/todo/%d/toggle"] button', $todoId));
        $clicked = (bool) $this->js($c, 'const s=arguments[0]; const b=document.querySelector(s); if(b){b.click(); return true;} return false;', [sprintf('form[action*="/todo/%d/toggle"] button', $todoId)]);
        self::assertTrue($clicked, 'Toggle button not found.');
        $c->wait(20)->until(fn() => (bool) $this->js($c, 'const s=arguments[0]; const b=document.querySelector(s); return !!b && /Undo/i.test(b.textContent);', [sprintf('form[action*="/todo/%d/toggle"] button', $todoId)]));
        $c->refreshCrawler();

        $this->js($c, 'window.confirm = () => true;');
        $clickedDel = (bool) $this->js($c, 'const s=arguments[0]; const b=document.querySelector(s); if(b){b.click(); return true;} return false;', [sprintf('form[action*="/todo/%d/delete"] button', $todoId)]);
        self::assertTrue($clickedDel, 'Delete button not found.');

        $c->wait(25)->until(fn() =>
            (bool) $this->js($c, 'return !!document.querySelector(".alert.alert-success");')
            || !(bool) $this->js($c, 'const s=arguments[0]; return !!document.querySelector(s);', [sprintf('form[action*="/todo/%d/delete"]', $todoId)])
        );
        $c->refreshCrawler();

        self::assertStringNotContainsString($task, $c->getPageSource(), 'Todo should be removed after delete.');
    }
}
