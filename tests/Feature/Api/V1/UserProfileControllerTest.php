<?php

use App\Models\Barangay;
use App\Models\City;
use App\Models\Region;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows a profile for a user who has one', function () {
    actingAsOwner();

    $user = User::factory()->create();
    $profile = UserProfile::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson("/api/v1/users/{$user->id}/profile");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $profile->id)
        ->assertJsonPath('data.firstname', $profile->firstname);
});

it('returns a 404 envelope for a user with no profile', function () {
    actingAsOwner();

    $user = User::factory()->create();

    $response = $this->getJson("/api/v1/users/{$user->id}/profile");

    $response->assertStatus(404)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Profile not found');
});

it('creates a profile with a nested address', function () {
    actingAsOwner();

    $user = User::factory()->create();
    $region = Region::factory()->create();
    $city = City::factory()->create(['region_id' => $region->id, 'province_id' => null]);
    $barangay = Barangay::factory()->create(['city_id' => $city->id]);

    $response = $this->postJson("/api/v1/users/{$user->id}/profile", [
        'firstname' => 'Juan',
        'lastname' => 'Dela Cruz',
        'gender' => 'male',
        'address' => [
            'region_id' => $region->id,
            'city_id' => $city->id,
            'barangay_id' => $barangay->id,
            'address_line' => '123 Rizal St.',
            'postal_code' => '1000',
            'type' => 'primary',
            'is_default' => true,
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.firstname', 'Juan')
        ->assertJsonPath('data.lastname', 'Dela Cruz');

    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'firstname' => 'Juan',
        'lastname' => 'Dela Cruz',
        'gender' => 'male',
    ]);

    $profile = UserProfile::query()->where('user_id', $user->id)->firstOrFail();

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => UserProfile::class,
        'addressable_id' => $profile->id,
        'region_id' => $region->id,
        'city_id' => $city->id,
        'barangay_id' => $barangay->id,
        'address_line' => '123 Rizal St.',
    ]);
});

it('partially updates a profile and leaves omitted fields unchanged', function () {
    actingAsOwner();

    $user = User::factory()->create();
    $profile = UserProfile::factory()->create([
        'user_id' => $user->id,
        'firstname' => 'Maria',
        'lastname' => 'Santos',
        'middlename' => 'Reyes',
        'gender' => 'female',
    ]);

    $response = $this->putJson("/api/v1/users/{$user->id}/profile", [
        'gender' => 'other',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.gender', 'other')

        ->assertJsonPath('data.firstname', 'Maria')
        ->assertJsonPath('data.lastname', 'Santos')
        ->assertJsonPath('data.middlename', 'Reyes');

    $this->assertDatabaseHas('user_profiles', [
        'id' => $profile->id,
        'firstname' => 'Maria',
        'lastname' => 'Santos',
        'middlename' => 'Reyes',
        'gender' => 'other',
    ]);
});

it('uploads, replaces, and removes a user profile photo', function () {
    Storage::fake('public');
    actingAsOwner();

    $user = User::factory()->create();

    $response = $this->postJson("/api/v1/user/{$user->id}/photo", [
        'photo' => UploadedFile::fake()->image('avatar.jpg', 320, 320),
    ])->assertOk();

    $firstPath = $user->fresh()->profile?->profile_photo_path;

    expect($firstPath)->not->toBeNull()
        ->and($response->json('data.profile.photo_url'))->toContain('/storage/'.$firstPath);

    Storage::disk('public')->assertExists($firstPath);

    $this->postJson("/api/v1/user/{$user->id}/photo", [
        'photo' => UploadedFile::fake()->image('replacement.png', 320, 320),
    ])->assertOk();

    $secondPath = $user->fresh()->profile?->profile_photo_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    $this->deleteJson("/api/v1/user/{$user->id}/photo")
        ->assertOk()
        ->assertJsonPath('data.profile.photo_url', null);

    expect($user->fresh()->profile?->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});
