<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PesanValidasiTest extends TestCase
{
    public function test_aturan_umum_memakai_pesan_bahasa_indonesia(): void
    {
        $aturan = [
            'a' => 'in:x,y', 'b' => 'integer', 'c' => 'numeric', 'd' => 'date', 'e' => 'boolean', 'f' => 'array',
            'g' => 'url', 'h' => 'between:1,5', 'i' => 'same:a', 'j' => 'after_or_equal:today', 'k' => 'size:3',
        ];
        $galat = Validator::make(
            ['a' => 'z', 'b' => 'x', 'c' => 'x', 'd' => 'x', 'e' => 'x', 'f' => 'x', 'g' => 'x', 'h' => 'abcdefgh', 'i' => 'q', 'j' => '2000-01-01', 'k' => 'ab'],
            $aturan,
        )->errors()->all();

        $this->assertCount(count($aturan), $galat);
        foreach ($galat as $pesan) {
            $this->assertDoesNotMatchRegularExpression('/\b(The|must|invalid|selected|field)\b/', $pesan, $pesan);
        }
    }
}
