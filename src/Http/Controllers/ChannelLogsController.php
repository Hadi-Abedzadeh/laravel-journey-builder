<?php

namespace OpenJourney\LaravelJourneyBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class ChannelLogsController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate([
            'channel'=>'nullable|in:email,webpush',
            'status'=>'nullable|in:pending,sending,sent,failed,unknown,retry_authorized',
            'flow'=>'nullable|integer|min:1',
        ]);
        $db=DB::connection(config('journey_builder.connection','sqlsrv'));
        $q=$db->table('jb_channel_dispatches');
        if (!empty($filters['channel'])) $q->where('channel',$filters['channel']);
        if (!empty($filters['status'])) $q->where('status',$filters['status']);
        if (!empty($filters['flow'])) $q->where('flow_id',$filters['flow']);
        $rows=$q->select(['id','flow_id','run_id','channel','node_id','provider_id','status','attempts','last_error','created_at','sent_at'])
            ->orderByDesc('id')->paginate(40)->withQueryString();
        $counts=$db->table('jb_channel_dispatches')
            ->selectRaw('channel,status,COUNT_BIG(*) AS total')
            ->groupBy('channel','status')->get();
        return response()->view('journey-builder::channel-logs',compact('rows','counts','filters'))
            ->header('Cache-Control','no-store, private');
    }
}
