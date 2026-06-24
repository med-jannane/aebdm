<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            // Primary Key (id b auto-increment)
            $table->id();

            // Clé étrangère: Katlier had la commande m3a chi user. 
            // 'constrained()' katsawb la relation m3a table 'users', w 'cascadeOnDelete()' kat3ni ila tmsa7 l'user, kaytms7o 7ta les commandes dyalo automatiquement.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Clé étrangère okhra sans cascade mtalan l chi table 'categories'. 'nullable()' kat3ni t9der tkon khawya, w 'nullOnDelete()' katردha null ila tms7at l categorie.
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();

            // String fih max 50 caractères w khasso ykon unique (Reference mayt3awdch)
            $table->string('reference', 50)->unique();

            // Text t9der tkon khawya (nullable) ila l'utilisateur maktb walo
            $table->text('description')->nullable();

            // Decimal l flous: 8 ar9am f total, 2 mn wra lfasila (ex: 123456.78)
            $table->decimal('prix_total', 8, 2);

            // Enum: La colonne katchd ghir had les 3 valeurs li 7ddti liha, w par défaut katkon 'en_attente'
            $table->enum('statut', ['en_attente', 'validee', 'annulee'])->default('en_attente');

            // Boolean (tinyint f MySQL): True wla False, par défaut False (0)
            $table->boolean('est_paye')->default(false);

            // Katcreer lik 'created_at' w 'updated_at' automatiquement
            $table->timestamps();

            // Katcreer colonne 'deleted_at'. L'ach katli9 ? Bach mli tmsa7 l commande, tb9a f l base de données w maychofhach l'utilisateur (Soft Delete).
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hna kandiro l3aks. f 'down', kanms7o l table ila drna 'php artisan migrate:rollback'.
        Schema::dropIfExists('commandes');
    }
};