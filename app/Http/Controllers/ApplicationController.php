<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ProposalBreak;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     * 申請一覧画面の表示（一般ユーザー・管理者共通のパス）
     */
    public function index(): View
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $applications = Application::with('user', 'AttendanceRecord')
                ->latest('application_date')
                ->get();

            return view('admin.admin-application-list', [
                'applications' => $applications,
            ]);
        }

        $formattedApplications = $user->applications()
            ->with('AttendanceRecord')
            ->latest('application_date')
            ->get()
            ->map(fn (Application $application) => [
                'id' => $application->id,
                'approval_status' => $application->approval_status,
                'date' => $application->AttendanceRecord->date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => $application->application_date->format('Y/m/d'),
            ]);

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $formattedApplications,
        ]);
    }

    /**
     * Display the specified resource.
     * 修正申請承認画面の表示（管理者専用）
     */
    public function show(int $id): View
    {
        $application = Application::with('user', 'AttendanceRecord', 'proposalBreaks')->findOrFail($id);

        return view('admin.admin-application-detail', [
            'user' => $application->user,
            'application' => $application,
        ]);
    }

    /**
     * 修正申請を承認し、勤怠情報へ反映する（管理者専用）
     */
    public function approve(int $id): RedirectResponse
    {
        $application = Application::with('AttendanceRecord', 'proposalBreaks')->findOrFail($id);

        if ($application->approval_status === '承認待ち') {
            DB::transaction(function () use ($application): void {
                $attendanceRecord = $application->AttendanceRecord;

                $attendanceRecord->update([
                    'clock_in' => $application->getRawOriginal('new_clock_in'),
                    'clock_out' => $application->getRawOriginal('new_clock_out'),
                    'comment' => $application->comment,
                ]);

                $attendanceRecord->breaks()->delete();

                $application->proposalBreaks
                    ->filter(fn (ProposalBreak $break) => $break->break_in !== null)
                    ->each(fn (ProposalBreak $break) => $attendanceRecord->breaks()->create([
                        'break_in' => $break->break_in,
                        'break_out' => $break->break_out,
                    ]));

                $application->update(['approval_status' => '承認済み']);
            });
        }

        return redirect('/stamp_correction_request/list');
    }

    /**
     * 一般ユーザーの申請一覧「詳細」リンクを、対応する勤怠詳細画面へ橋渡しする
     */
    public function redirectToAttendanceDetail(int $id): RedirectResponse
    {
        $application = Application::where('user_id', Auth::id())->findOrFail($id);

        return redirect("/attendance/{$application->attendance_record_id}");
    }
}
