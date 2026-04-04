<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('newsletter_subscribers');

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('locale')->default('en');
            $table->boolean('is_confirmed')->default(false);
            $table->string('token', 100)->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('newsletter_subscribers');

        parent::tearDown();
    }

    public function test_it_stores_a_subscriber_sends_confirmation_and_returns_expected_json(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/newsletter/subscribe', [
            'email' => 'test@example.com',
        ]);

        $response
            ->assertCreated()
            ->assertExactJson([
                'message' => 'Please check your email to confirm your subscription',
            ]);

        $subscriber = NewsletterSubscriber::where('email', 'test@example.com')->first();

        $this->assertNotNull($subscriber);
        $this->assertNotNull($subscriber->token);
        $this->assertFalse($subscriber->is_confirmed);
        $this->assertSame(1, DB::table('newsletter_subscribers')->count());

        Mail::assertSent(NewsletterConfirmation::class, function (NewsletterConfirmation $mail) use ($subscriber) {
            return $mail->hasTo('test@example.com')
                && $mail->hasReplyTo('online@takeovercreatives.com')
                && $mail->confirmUrl === url("/api/v1/newsletter/confirm/{$subscriber->token}");
        });
    }
}
