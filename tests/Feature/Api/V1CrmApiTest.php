<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerSegment;
use App\Models\CustomerVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1CrmApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();

        $this->customer = Customer::create([
            'name' => 'Customer CRM',
            'no_telp' => '08123456789',
            'address' => 'Jl. CRM No. 1',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function memberPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Member CRM '.uniqid(),
            'no_telp' => '08'.uniqid(),
            'address' => 'Jl. Member No. 1',
            'province_id' => '32',
            'regency_id' => '32.73',
            'district_id' => '32.73.01',
            'village_id' => '32.73.01.1001',
        ], $overrides);
    }

    public function test_members_index_lists_customers(): void
    {
        $this->customer->update(['is_loyalty_member' => true, 'member_code' => 'MBR-001']);

        $response = $this->getJson('/api/v1/members');

        $response->assertOk()
            ->assertJsonPath('data.members.total', 1)
            ->assertJsonPath('data.members.data.0.name', 'Customer CRM');
    }

    public function test_member_store_creates_customer(): void
    {
        $response = $this->postJson('/api/v1/members', $this->memberPayload());

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', [
            'no_telp' => $response->json('data.no_telp'),
        ]);
    }

    public function test_member_store_requires_customers_create_ability(): void
    {
        Sanctum::actingAs($this->user, ['customers-access']);

        $response = $this->postJson('/api/v1/members', $this->memberPayload());

        $response->assertForbidden();
    }

    public function test_member_show_returns_details(): void
    {
        $response = $this->getJson("/api/v1/members/{$this->customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.member.id', $this->customer->id);
    }

    public function test_member_update_changes_address(): void
    {
        $response = $this->putJson("/api/v1/members/{$this->customer->id}", $this->memberPayload([
            'name' => 'Customer CRM Updated',
            'no_telp' => $this->customer->no_telp,
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'name' => 'Customer CRM Updated',
        ]);
    }

    public function test_voucher_store_generates_code_when_missing(): void
    {
        $response = $this->postJson('/api/v1/customer-vouchers', [
            'customer_id' => $this->customer->id,
            'name' => 'Voucher Diskon 50K',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 50000,
        ]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.code'));
        $this->assertDatabaseHas('customer_vouchers', [
            'id' => $response->json('data.id'),
            'discount_value' => 50000,
        ]);
    }

    public function test_voucher_update_changes_value(): void
    {
        $voucher = CustomerVoucher::create([
            'customer_id' => $this->customer->id,
            'code' => 'VCR-TEST01',
            'name' => 'Voucher Test',
            'discount_type' => CustomerVoucher::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/customer-vouchers/{$voucher->id}", [
            'customer_id' => $this->customer->id,
            'code' => 'VCR-TEST01',
            'name' => 'Voucher Test',
            'discount_type' => CustomerVoucher::TYPE_PERCENTAGE,
            'discount_value' => 25,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('customer_vouchers', [
            'id' => $voucher->id,
            'discount_value' => 25,
        ]);
    }

    public function test_voucher_destroy_deletes(): void
    {
        $voucher = CustomerVoucher::create([
            'customer_id' => $this->customer->id,
            'code' => 'VCR-DEL01',
            'name' => 'Voucher Del',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10000,
        ]);

        $response = $this->deleteJson("/api/v1/customer-vouchers/{$voucher->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_vouchers', ['id' => $voucher->id]);
    }

    public function test_voucher_destroy_requires_delete_ability(): void
    {
        $voucher = CustomerVoucher::create([
            'customer_id' => $this->customer->id,
            'code' => 'VCR-DEL02',
            'name' => 'Voucher Del2',
            'discount_type' => CustomerVoucher::TYPE_FIXED_AMOUNT,
            'discount_value' => 10000,
        ]);
        Sanctum::actingAs($this->user, ['customer-vouchers-access']);

        $this->deleteJson("/api/v1/customer-vouchers/{$voucher->id}")->assertForbidden();
    }

    public function test_segment_crud_flow(): void
    {
        $response = $this->postJson('/api/v1/customer-segments', [
            'name' => 'Segment VIP Manual',
            'type' => CustomerSegment::TYPE_MANUAL,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $segmentId = $response->json('data.id');
        $this->assertDatabaseHas('customer_segments', ['id' => $segmentId, 'type' => 'manual']);

        $show = $this->getJson("/api/v1/customer-segments/{$segmentId}");
        $show->assertOk()
            ->assertJsonPath('data.segment.id', $segmentId);

        $update = $this->putJson("/api/v1/customer-segments/{$segmentId}", [
            'name' => 'Segment VIP Renamed',
            'type' => CustomerSegment::TYPE_MANUAL,
        ]);
        $update->assertOk();
        $this->assertDatabaseHas('customer_segments', [
            'id' => $segmentId,
            'name' => 'Segment VIP Renamed',
        ]);

        $destroy = $this->deleteJson("/api/v1/customer-segments/{$segmentId}");
        $destroy->assertNoContent();
        $this->assertDatabaseMissing('customer_segments', ['id' => $segmentId]);
    }

    public function test_segment_add_and_remove_member(): void
    {
        $segment = CustomerSegment::create([
            'name' => 'Segment Manual Add',
            'slug' => 'segment-manual-add',
            'type' => CustomerSegment::TYPE_MANUAL,
            'is_active' => true,
        ]);

        $add = $this->postJson("/api/v1/customer-segments/{$segment->id}/members", [
            'customer_id' => $this->customer->id,
        ]);
        $add->assertOk();
        $this->assertDatabaseHas('customer_segment_memberships', [
            'customer_segment_id' => $segment->id,
            'customer_id' => $this->customer->id,
        ]);

        $remove = $this->deleteJson("/api/v1/customer-segments/{$segment->id}/members/{$this->customer->id}");
        $remove->assertOk();
        $this->assertDatabaseMissing('customer_segment_memberships', [
            'customer_segment_id' => $segment->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_segment_member_add_rejected_for_auto_segment(): void
    {
        $segment = CustomerSegment::create([
            'name' => 'Segment Auto',
            'slug' => 'segment-auto',
            'type' => CustomerSegment::TYPE_AUTO,
            'auto_rule_type' => CustomerSegment::RULE_SPENDING,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/customer-segments/{$segment->id}/members", [
            'customer_id' => $this->customer->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_campaign_store_as_draft(): void
    {
        $response = $this->postJson('/api/v1/crm-campaigns', [
            'name' => 'Promo Akhir Tahun',
            'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
            'channel' => CustomerCampaign::CHANNEL_INTERNAL,
            'message_template' => 'Halo {nama}, diskon spesial!',
            'audience_filters' => ['customer_type' => 'all'],
            'save_as_draft' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customer_campaigns', [
            'id' => $response->json('data.id'),
            'status' => CustomerCampaign::STATUS_DRAFT,
        ]);
    }

    public function test_campaign_store_processes_immediately_when_not_draft(): void
    {
        $response = $this->postJson('/api/v1/crm-campaigns', [
            'name' => 'Promo Langsung',
            'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
            'channel' => CustomerCampaign::CHANNEL_INTERNAL,
            'message_template' => 'Halo!',
            'audience_filters' => ['customer_type' => 'all'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customer_campaigns', [
            'id' => $response->json('data.id'),
            'status' => CustomerCampaign::STATUS_READY,
        ]);
    }

    public function test_campaign_process_then_cancel(): void
    {
        $campaign = CustomerCampaign::create([
            'name' => 'Campaign Process',
            'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
            'channel' => CustomerCampaign::CHANNEL_INTERNAL,
            'status' => CustomerCampaign::STATUS_DRAFT,
        ]);

        $process = $this->postJson("/api/v1/crm-campaigns/{$campaign->id}/process");
        $process->assertOk();
        $this->assertDatabaseHas('customer_campaigns', [
            'id' => $campaign->id,
            'status' => CustomerCampaign::STATUS_READY,
        ]);

        $cancel = $this->postJson("/api/v1/crm-campaigns/{$campaign->id}/cancel");
        $cancel->assertOk();
        $this->assertDatabaseHas('customer_campaigns', [
            'id' => $campaign->id,
            'status' => CustomerCampaign::STATUS_CANCELLED,
        ]);
    }

    public function test_campaign_index_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/crm-campaigns')->assertForbidden();
    }

    public function test_reminders_index_lists_reminders(): void
    {
        $response = $this->getJson('/api/v1/crm-reminders');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_reminders_index_requires_access_ability(): void
    {
        Sanctum::actingAs($this->user, ['products-access']);

        $this->getJson('/api/v1/crm-reminders')->assertForbidden();
    }
}
