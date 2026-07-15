<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_main_folders')) {
            Schema::create('document_main_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_subfolders')) {
            Schema::create('document_subfolders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('main_folder_id')->constrained('document_main_folders')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['main_folder_id', 'name']);
            });
        }

        if (DB::table('document_main_folders')->exists()) {
            return;
        }

        $tree = [
            'Financial Documents' => [
                'Bank Gurantees',
                'Invoice',
                'Payment Voucher',
                'Proforma Invoice',
                'Receipt Voucher',
                'Sales Credit Note',
                'Supplier Delivery Note',
                'Supplier Invoice',
                'Supplier Time Sheets',
            ],
            'General Correspondence' => [
                'Incoming Or Outgoing Letter',
                'Internal Memo',
                'KPI Report',
                'Monthly Report',
                'Payment Certificate',
                'Project Award Notification',
                'Snags',
                'Spare Parts',
                'Permit and NOC',
            ],
            'Project Correspondence' => [
                'BOQ Bill Of Quantities',
                'Defect Liability Certificate',
                'Engineers Correspondences',
                'Engineers Instruction',
                'MOM',
                'NCR',
                'Operation And Maintenance Manual',
                'Payment Application',
                'Quality Observation Report',
                'Request For Information',
                'Site Observation Report',
                'Site Incident Report',
                'Taking Over Certificate',
                'Testing And Commissioning',
                'Variation',
                'Warranty By Us',
                'Design Calculation',
                'Confirmation Of Verbal Instruction',
                'Project Technical Documents',
            ],
            'Purchase Documents' => [
                'Catalogs',
                'Delivery Order',
                'Enquireis',
                'Good Receipt Note',
                'Material Issue Note',
                'Material Return Note',
                'Purchase Order',
                'Purchase Request',
                'Quotations',
                'Sales Order',
                'Trade License certificate',
                'VAT Registration Certificate',
                'Vendor Registration certificate',
            ],
            'Transmittals Documents' => [
                'As Built Drawing Submittal',
                'Material Submittal',
                'Material Inspection Request',
                'Method Statement',
                'Prequalification',
                'Shop Drawing',
                'Work Inspection',
                'Document Transmittal',
                'Material Sample',
            ],
        ];

        $now = now();
        $mainSort = 0;
        foreach ($tree as $mainName => $subs) {
            $mainId = DB::table('document_main_folders')->insertGetId([
                'name' => $mainName,
                'sort_order' => $mainSort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $subSort = 0;
            foreach ($subs as $subName) {
                DB::table('document_subfolders')->insert([
                    'main_folder_id' => $mainId,
                    'name' => $subName,
                    'sort_order' => $subSort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_subfolders');
        Schema::dropIfExists('document_main_folders');
    }
};
