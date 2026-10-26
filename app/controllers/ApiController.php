<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContractionPresenter;
use App\Services\ContractionService;
use App\Services\StatsService;
use App\Services\TimeService;

/** Endpoint JSON. Semua respons memakai Cache-Control: no-store (lihat Response). */
final class ApiController extends Controller
{
    /** Status dashboard: kontraksi aktif, ringkasan, grafik, riwayat terbaru. */
    public function state(): void
    {
        Response::json(['ok' => true, 'user_id' => $this->userId()] + (new StatsService())->dashboard($this->userId()));
    }

    /** Token CSRF untuk halaman offline (statis) yang perlu menyinkronkan antrean. */
    public function csrf(): void
    {
        Response::json(['ok' => true, 'token' => Csrf::token(), 'user_id' => $this->userId()]);
    }

    /**
     * Mulai / akhiri / sinkronkan kontraksi (idempoten berdasarkan uuid).
     * Body: {uuid, started_at (epoch ms), ended_at (epoch ms|null), notes}
     */
    public function sync(): void
    {
        $userId = $this->userId();
        $result = (new ContractionService())->sync(
            $userId,
            Request::input('uuid'),
            Request::input('started_at'),
            Request::input('ended_at'),
            Request::input('notes')
        );
        $state = (new StatsService())->dashboard($userId);
        Response::json([
            'ok'          => true,
            'saved'       => true,
            'created'     => $result['created'],
            'finished'    => $result['finished'],
            'contraction' => ContractionPresenter::toArray($result['contraction']),
            'state'       => $state,
        ], $result['created'] ? 201 : 200);
    }

    public function cancel(): void
    {
        $userId = $this->userId();
        $deleted = (new ContractionService())->cancelActive($userId, Request::input('uuid'));
        Response::json(['ok' => true, 'deleted' => $deleted, 'state' => (new StatsService())->dashboard($userId)]);
    }

    /** Edit kontraksi selesai. Tanggal: YYYY-MM-DD / DD-MM-YYYY, waktu: HH:mm:ss (zona lokal). */
    public function update(): void
    {
        $userId = $this->userId();
        $id = filter_var(Request::input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(422, 'ID kontraksi tidak valid.');
        }
        $start = TimeService::parseLocalDateTime((string) Request::input('start_date'), (string) Request::input('start_time'));
        $end = TimeService::parseLocalDateTime(
            (string) (Request::input('end_date') ?: Request::input('start_date')),
            (string) Request::input('end_time')
        );
        $row = (new ContractionService())->update($userId, $id, $start, $end, Request::input('notes'));
        Response::json(['ok' => true, 'message' => 'Perubahan tersimpan.', 'contraction' => ContractionPresenter::toArray($row)]);
    }

    public function delete(): void
    {
        $userId = $this->userId();
        $id = filter_var(Request::input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HttpException(422, 'ID kontraksi tidak valid.');
        }
        (new ContractionService())->delete($userId, $id);
        Response::json(['ok' => true, 'message' => 'Data kontraksi dihapus.']);
    }

    public function endSession(): void
    {
        $userId = $this->userId();
        $session = (new ContractionService())->endSession($userId);
        Response::json([
            'ok'      => true,
            'ended'   => $session !== null,
            'message' => $session ? 'Sesi pencatatan ditutup.' : 'Tidak ada sesi aktif.',
            'state'   => (new StatsService())->dashboard($userId),
        ]);
    }
}
