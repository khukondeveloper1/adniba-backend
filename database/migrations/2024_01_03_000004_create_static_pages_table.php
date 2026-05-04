<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()
                  ->comment('about|privacy_policy|terms_conditions|contact');
            $table->string('title', 200);
            $table->longText('content_html');
            $table->tinyInteger('is_published')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')
                  ->references('id')->on('admin_users')
                  ->onDelete('set null');
        });

        // Seed default pages
        $pages = [
            [
                'key'          => 'about',
                'title'        => 'About AdNiba',
                'content_html' => '<h1>About AdNiba</h1><p>AdNiba is a powerful Ad Network Management and Mediation Platform designed for mobile app developers.</p>',
            ],
            [
                'key'          => 'privacy_policy',
                'title'        => 'Privacy Policy',
                'content_html' => '<h1>Privacy Policy</h1><p>This privacy policy explains how AdNiba collects and uses your data.</p>',
            ],
            [
                'key'          => 'terms_conditions',
                'title'        => 'Terms & Conditions',
                'content_html' => '<h1>Terms & Conditions</h1><p>By using AdNiba, you agree to these terms and conditions.</p>',
            ],
        ];

        foreach ($pages as $page) {
            DB::table('static_pages')->insert(array_merge($page, [
                'is_published' => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
