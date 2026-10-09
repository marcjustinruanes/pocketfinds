<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds hero banner fields to the settings table so the admin can control
 * the landing-page hero image, tagline, subtitle, and seasonal label
 * without touching code — seasonal/thematic campaigns are swapped from the
 * admin Settings → Hero Banner panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('hero_image')->nullable()->after('email_notifications_enabled');
            $table->string('hero_label', 100)->nullable()->default('Local Marketplace · Philippines')->after('hero_image');
            $table->string('hero_tagline', 200)->nullable()->default('Find It. Love It. Pocket It.')->after('hero_label');
            $table->string('hero_subtitle', 500)->nullable()->default('Browse products from verified local sellers — pet supplies, electronics, fashion, home essentials, and more.')->after('hero_tagline');
            $table->string('hero_cta_text', 80)->nullable()->default('Browse Products')->after('hero_subtitle');
            $table->string('hero_overlay', 30)->nullable()->default('dark')->after('hero_cta_text'); // dark | light | none
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['hero_image', 'hero_label', 'hero_tagline', 'hero_subtitle', 'hero_cta_text', 'hero_overlay']);
        });
    }
};
