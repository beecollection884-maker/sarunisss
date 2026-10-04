<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminAuthPageTest extends TestCase
{
    public function test_single_auth_pages_are_accessible(): void
    {
        foreach (['admin', 'guru-mapel', 'walikelas', 'orang-tua', 'siswa'] as $portal) {
            $this->get("/auth?portal={$portal}")
                ->assertOk()
                ->assertSee('Masuk')
                ->assertSee('dashboard sesuai role akun')
                ->assertSee('/login', false)
                ->assertDontSee('Pilih Portal')
                ->assertDontSee('data-portal-select', false);

            $this->get("/auth/verifikasi-email?portal={$portal}")
                ->assertOk()
                ->assertSee('Verifikasi Email');

            $this->get("/auth/verifikasi-kode?portal={$portal}")
                ->assertOk()
                ->assertSee('Verifikasi Email');

            $this->get("/auth/lupa-kata-sandi?portal={$portal}")
                ->assertOk()
                ->assertSee('Lupa Kata Sandi');
        }
    }
}
