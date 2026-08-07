<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class UndanganController extends Controller
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.undangan.base_url'), '/');
        $this->apiKey = (string) config('services.undangan.api_key');
    }

    /**
     * Client HTTP untuk API Undangan Cetak (wayaenikah.com).
     */
    protected function client()
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->baseUrl($this->baseUrl)->timeout(60);
    }

    /**
     * Tampilkan daftar undangan cetak.
     *
     * Semua data diambil sekaligus (di-loop per halaman API, maks 100/halaman)
     * lalu dikirim ke frontend sebagai array flat. Search/filter/sort/pagination
     * ditangani client-side agar seluruh data (lebih dari 50) selalu tampil.
     */
    public function index(Request $request)
    {
        $allData = [];
        $apiError = null;
        $page = 1;
        $lastPage = 1;
        $total = null;

        do {
            $response = $this->client()->get('/undangan-cetak', ['per_page' => 100, 'page' => $page]);

            if ($response->failed()) {
                $body = $response->json();
                $apiError = $body['message'] ?? 'Gagal mengambil data undangan dari server.';

                break;
            }

            $data = $response->json()['data'] ?? null;

            if (! $data) {
                break;
            }

            $items = $data['data'] ?? [];
            $allData = array_merge($allData, $items);
            $lastPage = (int) ($data['last_page'] ?? $page);
            $total = (int) ($data['total'] ?? ($total ?? count($allData)));
            $page++;
        } while ($page <= $lastPage && count($allData) < $total);

        return Inertia::render('Undangan/Index', [
            'undangan' => $allData,
            'apiError' => $apiError,
        ]);
    }

    /**
     * Tambah undangan cetak baru.
     */
    public function store(Request $request)
    {
        $files = collect($request->file('gambar', []))
            ->flatten()
            ->filter(fn ($file) => $file instanceof UploadedFile);

        $http = $this->client();
        foreach ($files as $file) {
            $http = $http->attach('gambar[]', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName());
        }

        $response = $http->post('/undangan-cetak', $request->except(['gambar', '_token']));
        $body = $response->json() ?? [];

        if ($response->failed()) {
            return $this->handleApiError($response->status(), $body, 'menambah');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $body['message'] ?? 'Undangan cetak berhasil ditambahkan.',
        ]);

        return redirect()->route('undangan.index');
    }

    /**
     * Perbarui undangan cetak.
     */
    public function update(Request $request, $id)
    {
        $id = (int) $id;

        // 1. Hapus gambar per index (jika user menandai penghapusan di form edit)
        $hapusGambar = $request->input('hapus_gambar', []);
        if (is_string($hapusGambar)) {
            $hapusGambar = array_filter(explode(',', $hapusGambar), fn ($v) => is_numeric($v));
        }
        $hapusGambar = collect($hapusGambar)->map(fn ($v) => (int) $v)->sortDesc()->values();

        foreach ($hapusGambar as $imageIndex) {
            $this->client()->delete("/undangan-cetak/{$id}/gambar/{$imageIndex}");
        }

        // 2. Siapkan payload update
        $files = collect($request->file('gambar', []))
            ->flatten()
            ->filter(fn ($file) => $file instanceof UploadedFile);

        $payload = $request->except(['gambar', '_method', '_token', 'hapus_gambar', 'hapus_gambar_lama']);

        if ($files->isNotEmpty()) {
            $http = $this->client();
            foreach ($files as $file) {
                $http = $http->attach('gambar[]', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName());
            }

            if ($request->boolean('hapus_gambar_lama')) {
                $payload['hapus_gambar_lama'] = 'true';
            }
            $payload['_method'] = 'PUT';

            $response = $http->post("/undangan-cetak/{$id}", $payload);
        } else {
            $response = $this->client()->asJson()->put("/undangan-cetak/{$id}", $payload);
        }

        $body = $response->json() ?? [];

        if ($response->failed()) {
            return $this->handleApiError($response->status(), $body, 'memperbarui');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $body['message'] ?? 'Undangan cetak berhasil diperbarui.',
        ]);

        return redirect()->route('undangan.index');
    }

    /**
     * Hapus undangan cetak beserta semua gambarnya.
     */
    public function destroy($id)
    {
        $response = $this->client()->delete('/undangan-cetak/'.(int) $id);
        $body = $response->json() ?? [];

        if ($response->failed()) {
            return $this->handleApiError($response->status(), $body, 'menghapus');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $body['message'] ?? 'Undangan cetak berhasil dihapus.',
        ]);

        return redirect()->route('undangan.index');
    }

    /**
     * Hapus satu gambar undangan berdasarkan index (0-based).
     */
    public function destroyImage($id, $imageIndex)
    {
        $response = $this->client()->delete('/undangan-cetak/'.(int) $id.'/gambar/'.(int) $imageIndex);
        $body = $response->json() ?? [];

        if ($response->failed()) {
            return $this->handleApiError($response->status(), $body, 'menghapus gambar');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $body['message'] ?? 'Gambar berhasil dihapus.',
        ]);

        return redirect()->route('undangan.index');
    }

    /**
     * Tangani error dari API eksternal.
     */
    protected function handleApiError(int $status, array $body, string $action)
    {
        $message = $body['message'] ?? 'Terjadi kesalahan saat '.$action.' data undangan.';
        $errors = $body['errors'] ?? [];

        if ($status === 422 && is_array($errors)) {
            return back()->withErrors($errors)->with('error', $message);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
