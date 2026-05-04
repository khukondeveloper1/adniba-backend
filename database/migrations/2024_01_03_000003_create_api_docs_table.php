<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_docs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique()->comment('URL-friendly identifier');
            $table->string('category', 100)->default('general')
                  ->comment('general|authentication|endpoints|examples');
            $table->longText('content_html')->comment('HTML content written by admin');
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('is_published')->default(1);
            $table->unsignedBigInteger('created_by')->nullable()
                  ->comment('admin_users.id');
            $table->unsignedBigInteger('updated_by')->nullable()
                  ->comment('admin_users.id');
            $table->timestamps();

            $table->index(['category', 'is_published']);

            $table->foreign('created_by')
                  ->references('id')->on('admin_users')
                  ->onDelete('set null');
            $table->foreign('updated_by')
                  ->references('id')->on('admin_users')
                  ->onDelete('set null');
        });

        // Seed default documentation
        DB::table('api_docs')->insert([
            [
                'title'        => 'External API Overview',
                'slug'         => 'external-api-overview',
                'category'     => 'general',
                'content_html' => '<h1>AdNiba External API</h1>
<p>The AdNiba External API allows developers to manage their apps programmatically from their own systems.</p>
<h2>Base URL</h2>
<pre><code>http://your-domain.com/api/v1/external</code></pre>
<h2>Authentication</h2>
<p>All requests require your App\'s API Key in the header:</p>
<pre><code>x-api-key: adniba_your_api_key_here</code></pre>
<h2>Response Format</h2>
<p>All responses follow the standard JSON format:</p>
<pre><code>{
  "status": "ok",
  "data": { ... }
}</code></pre>',
                'sort_order'   => 1,
                'is_published' => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('api_docs');
    }
};
