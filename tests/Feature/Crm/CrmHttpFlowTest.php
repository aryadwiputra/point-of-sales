<?php

namespace Tests\Feature\Crm;

use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\CustomerSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmHttpFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'customer-segments-access',
            'customer-segments-update',
            'crm-campaigns-access',
            'crm-campaigns-create',
            'crm-campaigns-update',
            'crm-reminders-access',
        ] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_segment_index_filters_and_manual_membership_endpoints_work(): void
    {
        $user = $this->createUser([
            'customer-segments-access',
            'customer-segments-update',
        ]);
        $customer = $this->createCustomer();
        $segment = CustomerSegment::create([
            'name' => 'Priority Customer',
            'slug' => 'priority-customer',
            'type' => CustomerSegment::TYPE_MANUAL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('customer-segments.index', [
                'search' => 'Priority',
                'type' => CustomerSegment::TYPE_MANUAL,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/CustomerSegments/Index')
                ->has('segments.data', 1)
                ->where('segments.data.0.name', 'Priority Customer')
                ->where('filters.search', 'Priority')
                ->where('filters.type', CustomerSegment::TYPE_MANUAL));

        $this->actingAs($user)
            ->post(route('customer-segments.members.store', $segment), [
                'customer_id' => $customer->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customer_segment_memberships', [
            'customer_id' => $customer->id,
            'customer_segment_id' => $segment->id,
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->delete(route('customer-segments.members.destroy', [$segment, $customer]))
            ->assertRedirect();

        $this->assertDatabaseMissing('customer_segment_memberships', [
            'customer_id' => $customer->id,
            'customer_segment_id' => $segment->id,
        ]);
    }

    public function test_campaign_can_be_processed_immediately_and_completed_from_delivery_log(): void
    {
        $user = $this->createUser([
            'crm-campaigns-create',
            'crm-campaigns-update',
        ]);
        $customer = $this->createCustomer();

        $this->actingAs($user)
            ->post(route('crm-campaigns.store'), [
                'name' => 'Promo Weekend',
                'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
                'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                'message_template' => 'Halo {{name}}, promo weekend.',
                'save_as_draft' => false,
                'audience_filters' => [
                    'customer_type' => 'all',
                    'receivable_status' => 'all',
                    'voucher_filter' => 'all',
                ],
            ])
            ->assertRedirect();

        $campaign = CustomerCampaign::query()->where('name', 'Promo Weekend')->firstOrFail();
        $log = $campaign->logs()->firstOrFail();

        $this->assertSame(CustomerCampaign::STATUS_READY, $campaign->status);
        $this->assertSame($customer->id, $log->customer_id);
        $this->assertSame(CustomerCampaignLog::STATUS_READY_TO_SEND, $log->status);

        $this->actingAs($user)
            ->post(route('crm-campaign-logs.mark-sent', $log))
            ->assertRedirect();

        $this->assertDatabaseHas('customer_campaign_logs', [
            'id' => $log->id,
            'status' => CustomerCampaignLog::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('customer_campaigns', [
            'id' => $campaign->id,
            'status' => CustomerCampaign::STATUS_PROCESSED,
        ]);
    }

    public function test_reminder_queue_honors_type_filter(): void
    {
        $user = $this->createUser(['crm-reminders-access']);
        CustomerCampaign::create([
            'name' => 'Due Reminder',
            'type' => CustomerCampaign::TYPE_DUE_DATE_REMINDER,
            'status' => CustomerCampaign::STATUS_READY,
            'channel' => CustomerCampaign::CHANNEL_INTERNAL,
        ]);
        CustomerCampaign::create([
            'name' => 'Promo Broadcast',
            'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
            'status' => CustomerCampaign::STATUS_READY,
            'channel' => CustomerCampaign::CHANNEL_INTERNAL,
        ]);

        $this->actingAs($user)
            ->get(route('crm-reminders.index', [
                'type' => CustomerCampaign::TYPE_DUE_DATE_REMINDER,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/CrmReminders/Index')
                ->has('campaigns.data', 1)
                ->where('campaigns.data.0.name', 'Due Reminder')
                ->where('filters.type', CustomerCampaign::TYPE_DUE_DATE_REMINDER));
    }

    private function createUser(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createCustomer(): Customer
    {
        return Customer::create([
            'name' => 'CRM HTTP Customer',
            'no_telp' => '628111999000',
            'address' => 'Jl. CRM HTTP',
        ]);
    }
}
