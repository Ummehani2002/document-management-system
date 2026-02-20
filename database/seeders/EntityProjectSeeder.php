<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Project;
use Illuminate\Database\Seeder;

class EntityProjectSeeder extends Seeder
{
    /**
     * Create default Entity and Project so Upload has something to select.
     */
    public function run(): void
    {
        if (Entity::exists()) {
            return;
        }

        $entity = Entity::create(['name' => 'Main Company']);
        Project::create([
            'entity_id'       => $entity->id,
            'project_number'  => 'PSE20231011',
            'project_name'    => 'Landscape Project',
            'client_name'     => null,
            'consultant'     => null,
            'project_manager' => null,
            'document_controller' => null,
        ]);
    }
}
