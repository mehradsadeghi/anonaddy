<?php

namespace Tests\Feature;

use App\Alias;
use App\AliasRecipient;
use App\Domain;
use App\Mail\ForwardEmail;
use App\Notifications\NearBandwidthLimit;
use App\Recipient;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReceiveEmailTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create(['username' => 'johndoe']);
        $this->user->recipients()->save($this->user->defaultRecipient);
    }

    /** @test */
    public function it_can_forward_email_from_file()
    {
        Mail::fake();
        Notification::fake();

        Mail::assertNothingSent();

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '1000'
        ]);

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });

        Notification::assertNothingSent();
    }

    /** @test */
    public function it_can_forward_email_from_file_with_attachment()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_with_attachment.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['attachment@johndoe.anonaddy.me'],
                '--local_part' => ['attachment'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'attachment@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'attachment',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '1000'
        ]);

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    /** @test */
    public function it_can_forward_email_from_file_to_multiple_recipients()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_multiple_recipients.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.me', 'amazon@johndoe.anonaddy.me', 'paypal@johndoe.anonaddy.me'],
                '--local_part' => ['ebay', 'amazon', 'paypal'],
                '--extension' => ['', '', ''],
                '--domain' => ['johndoe.anonaddy.me', 'johndoe.anonaddy.me', 'johndoe.anonaddy.me'],
                '--size' => '1217'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('aliases', [
            'email' => 'amazon@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'amazon',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('aliases', [
            'email' => 'paypal@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'paypal',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertEquals(3, $this->user->aliases()->count());
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '1217'
        ]);

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    /** @test */
    public function it_can_forward_email_from_file_with_extension()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_with_extension.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay+a@johndoe.anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => ['a'],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '789'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '789'
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    /** @test */
    public function it_can_forward_email_with_existing_alias()
    {
        Mail::fake();

        Mail::assertNothingSent();

        factory(Alias::class)->create([
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
        ]);

        $defaultRecipient = $this->user->defaultRecipient;

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '559'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '559'
        ]);
        $this->assertCount(1, $this->user->aliases);

        Mail::assertQueued(ForwardEmail::class, function ($mail) use ($defaultRecipient) {
            return $mail->hasTo($defaultRecipient->email);
        });
    }

    /** @test */
    public function it_can_forward_email_with_existing_alias_and_receipients()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $alias = factory(Alias::class)->create([
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
        ]);

        $recipient = factory(Recipient::class)->create([
            'user_id' => $this->user->id,
            'email' => 'one@example.com'
        ]);

        $recipient2 = factory(Recipient::class)->create([
            'user_id' => $this->user->id,
            'email' => 'two@example.com'
        ]);

        AliasRecipient::create([
            'alias' => $alias,
            'recipient' => $recipient
        ]);

        AliasRecipient::create([
            'alias' => $alias,
            'recipient' => $recipient2
        ]);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '444'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '444'
        ]);

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo('one@example.com') &&
                   $mail->hasTo('two@example.com');
        });
    }

    /** @test */
    public function it_does_not_send_email_if_default_recipient_has_not_yet_been_verified()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $this->user->defaultRecipient->update(['email_verified_at' => null]);

        $this->assertNull($this->user->defaultRecipient->email_verified_at);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseMissing('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'emails_blocked' => 0
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '0'
        ]);

        Mail::assertNotSent(ForwardEmail::class);
    }

    /** @test */
    public function it_can_unsubscribe_alias_by_emailing_list_unsubscribe()
    {
        Mail::fake();

        Mail::assertNothingSent();

        factory(Alias::class)->create([
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain')
        ]);

        factory(Recipient::class)->create([
            'user_id' => $this->user->id,
            'email' => 'will@anonaddy.com'
        ]);

        $this->assertDatabaseHas('aliases', [
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'active' => true
        ]);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_unsubscribe.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['8f36380f-df4e-4875-bb12-9c4448573712@unsubscribe.anonaddy.me'],
                '--local_part' => ['8f36380f-df4e-4875-bb12-9c4448573712'],
                '--extension' => [''],
                '--domain' => ['unsubscribe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'active' => false
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '0'
        ]);

        Mail::assertNotSent(ForwardEmail::class);
    }

    /** @test */
    public function it_does_not_count_unsubscribe_recipient_when_calculating_size()
    {
        Mail::fake();

        Mail::assertNothingSent();

        factory(Alias::class)->create([
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain')
        ]);

        factory(Recipient::class)->create([
            'user_id' => $this->user->id,
            'email' => 'will@anonaddy.com'
        ]);

        $this->assertDatabaseHas('aliases', [
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'active' => true
        ]);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_unsubscribe_plus_other_recipient.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['8f36380f-df4e-4875-bb12-9c4448573712@unsubscribe.anonaddy.me', 'another@johndoe.anonaddy.me'],
                '--local_part' => ['8f36380f-df4e-4875-bb12-9c4448573712', 'another'],
                '--extension' => ['', ''],
                '--domain' => ['unsubscribe.anonaddy.me', 'johndoe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'active' => false
        ]);
        $this->assertDatabaseHas('aliases', [
            'user_id' => $this->user->id,
            'email' => 'another@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'another',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'active' => true
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '1000'
        ]);

        Mail::assertNotSent(ForwardEmail::class);
    }

    /** @test */
    public function it_cannot_unsubscribe_alias_if_not_a_verified_user_recipient()
    {
        Mail::fake();

        Mail::assertNothingSent();

        factory(Alias::class)->create([
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain')
        ]);

        $this->assertDatabaseHas('aliases', [
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'active' => true
        ]);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_unsubscribe.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['8f36380f-df4e-4875-bb12-9c4448573712@unsubscribe.anonaddy.me'],
                '--local_part' => ['8f36380f-df4e-4875-bb12-9c4448573712'],
                '--extension' => [''],
                '--domain' => ['unsubscribe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'id' => '8f36380f-df4e-4875-bb12-9c4448573712',
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'active' => true
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '0'
        ]);

        Mail::assertNotSent(ForwardEmail::class);
    }

    /** @test */
    public function it_can_forward_email_to_admin_username_for_root_domain()
    {
        Mail::fake();

        Mail::assertNothingSent();

        config(['anonaddy.admin_username' => 'johndoe']);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_admin.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['anonaddy.me'],
                '--size' => '1346'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '1346'
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    /** @test */
    public function it_can_forward_email_for_custom_domain()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $domain = factory(Domain::class)->create([
            'user_id' => $this->user->id,
            'domain' => 'example.com'
        ]);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_custom_domain.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@example.com'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['example.com'],
                '--size' => '871'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'domain_id' => $domain->id,
            'email' => 'ebay@example.com',
            'local_part' => 'ebay',
            'domain' => 'example.com',
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '871'
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });
    }

    /** @test */
    public function it_can_send_near_bandwidth_limit_notification()
    {
        Notification::fake();

        Notification::assertNothingSent();

        $this->user->update(['bandwidth' => 100943820]);

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.me'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.me'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '100944820'
        ]);

        Notification::assertSentTo(
            $this->user,
            NearBandwidthLimit::class
        );
    }

    /** @test */
    public function it_can_forward_email_from_file_for_all_domains()
    {
        Mail::fake();
        Notification::fake();

        Mail::assertNothingSent();

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_other_domain.eml'),
                '--sender' => 'will@anonaddy.com',
                '--recipient' => ['ebay@johndoe.anonaddy.com'],
                '--local_part' => ['ebay'],
                '--extension' => [''],
                '--domain' => ['johndoe.anonaddy.com'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => 'ebay@johndoe.anonaddy.com',
            'local_part' => 'ebay',
            'domain' => 'johndoe.anonaddy.com',
            'emails_forwarded' => 1,
            'emails_blocked' => 0
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'username' => 'johndoe',
            'bandwidth' => '1000'
        ]);

        Mail::assertQueued(ForwardEmail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });

        Notification::assertNothingSent();
    }
}
