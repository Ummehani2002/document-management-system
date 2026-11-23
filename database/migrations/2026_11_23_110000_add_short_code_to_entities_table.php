<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Known company short codes from the company register.
     *
     * @var array<string, string>
     */
    private array $knownCodes = [
        'metaline llc' => 'ML',
        'metaline' => 'ML',
        'proscape llc' => 'PS',
        'proscape' => 'PS',
        'proscape fm llc' => 'FM',
        'proscape fm' => 'FM',
        'proscape golf llc' => 'GF',
        'proscape golf' => 'GF',
        'proscape llc - sharjah' => 'PSSJ',
        'proscape llc sharjah' => 'PSSJ',
        'proscape international contracting company' => 'PK',
        'proscape international contracting llc' => 'PK',
        'proscape international contracting' => 'PK',
        'stones & slates llc' => 'SS',
        'stones and slates llc' => 'SS',
        'stones and slates' => 'SS',
        'tanseeq holding llc' => 'TH',
        'tanseeq holding' => 'TH',
        'tanseeq investment llc' => 'TI',
        'tanseeq investment' => 'TI',
        'tanseeq llc' => 'TQ',
        'tanseeq' => 'TQ',
        'tanseeq projects llc' => 'TP',
        'tanseeq projects' => 'TP',
        'tanseeq realty' => 'TR',
        'water in motion llc' => 'WM',
        'water in motion' => 'WM',
        'arteval interiors llc' => 'AV',
        'arteval interiors' => 'AV',
        'bioscape interiorscapes green walls and roofs llc' => 'BS',
        'bostan aljazirah trading llc' => 'BJ',
        'company accounts data' => 'DAT',
        'innolink engineering drawing services l.l.c' => 'IE',
        'innolink engineering drawing services llc' => 'IE',
        'landscape elements llc' => 'LE',
        'timbertech llc' => 'TT',
        'transmech llc' => 'TM',
        'urban design and landscape architecture fz-llc' => 'UD',
        'ventana llc' => 'VN',
        'acacia garden center llc' => 'GC',
        'acacia llc' => 'AP',
        'acacia trading company' => 'AK',
        'al aweer trading company' => 'AT',
        'apex infra contracting llc' => 'AI',
        'ahmad ar ancohi and mohamed nasar ahmed' => 'AN',
    ];

    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'short_code')) {
                $table->string('short_code', 16)->nullable()->after('name');
                $table->unique('short_code');
            }
        });

        $entities = DB::table('entities')->select('id', 'name', 'short_code')->get();
        foreach ($entities as $entity) {
            if (filled($entity->short_code ?? null)) {
                continue;
            }

            $code = $this->resolveCode((string) $entity->name);
            if ($code === null) {
                continue;
            }

            // Skip if another row already owns this code.
            $taken = DB::table('entities')
                ->where('short_code', $code)
                ->where('id', '!=', $entity->id)
                ->exists();
            if ($taken) {
                continue;
            }

            DB::table('entities')->where('id', $entity->id)->update(['short_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'short_code')) {
                $table->dropUnique(['short_code']);
                $table->dropColumn('short_code');
            }
        });
    }

    private function resolveCode(string $name): ?string
    {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        $key = str_replace(['&', '.'], ['and', ''], $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        if (isset($this->knownCodes[$key])) {
            return $this->knownCodes[$key];
        }

        $needles = array_keys($this->knownCodes);
        usort($needles, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($needles as $needle) {
            if ($key === $needle || str_starts_with($key, $needle.' ')) {
                return $this->knownCodes[$needle];
            }
        }

        return null;
    }
};
