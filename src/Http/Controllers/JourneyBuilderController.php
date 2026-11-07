<?php

namespace OpenJourney\LaravelJourneyBuilder\Http\Controllers;

use OpenJourney\LaravelJourneyBuilder\Services\Definition;
use OpenJourney\LaravelJourneyBuilder\Services\ApiGateway;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class JourneyBuilderController extends \Illuminate\Routing\Controller
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection', 'sqlsrv'));
    }

    // Dedicated list page; never mixes the management table with the drag/drop canvas.
    public function index()
    {
        $db=$this->db();
        $flows=$db->table('jb_flows')->orderByDesc('id')->paginate(30);
        $runRows=$db->table('jb_runs')->selectRaw('flow_id, state, count(*) as total')->groupBy('flow_id','state')->get();
        $smsRows=$db->table('jb_sms_logs')->selectRaw('flow_id, status, count(*) as total')->groupBy('flow_id','status')->get();
        $apiRows=$db->table('jb_api_logs')->selectRaw('flow_id, status, count(*) as total')->groupBy('flow_id','status')->get();
        $stats=[];
        foreach ($runRows as $r) $stats[$r->flow_id]['runs'][$r->state]=(int)$r->total;
        foreach ($smsRows as $r) $stats[$r->flow_id]['sms'][$r->status]=(int)$r->total;
        foreach ($apiRows as $r) $stats[$r->flow_id]['api'][$r->status]=(int)$r->total;
        return response()->view('journey-builder::index',compact('flows','stats'))->header('Cache-Control','no-store, private');
    }

    public function builder(int $id)
    {
        $flow=$this->db()->table('jb_flows')->where('id',$id)->first();
        abort_unless($flow,404);
        // Expose only existence of a secret, never its ciphertext or plaintext.
        $secretNodeIds=$this->db()->table('jb_api_credentials')->where('flow_id',$id)->pluck('node_id')->all();
        return response()->view('journey-builder::dashboard',compact('flow','secretNodeIds'))->header('Cache-Control','no-store, private');
    }

    public function show(Request $request, int $id)
    {
        $db=$this->db(); $flow=$db->table('jb_flows')->where('id',$id)->first(); abort_unless($flow,404);
        $filter=$request->validate(['state'=>'nullable|in:ready,waiting,exited,failed']);
        $base=$db->table('jb_runs')->where('flow_id',$id);
        if (!empty($filter['state'])) TypedWhere::eq($base, 'state', $filter['state'], 20);
        $runs=$base->orderByDesc('id')->paginate(40)->withQueryString();
        $runCounts=$db->table('jb_runs')->where('flow_id',$id)->selectRaw('state,count(*) as total')->groupBy('state')->pluck('total','state')->all();
        $smsCounts=$db->table('jb_sms_logs')->where('flow_id',$id)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status')->all();
        $apiCounts=$db->table('jb_api_logs')->where('flow_id',$id)->selectRaw('status,count(*) as total')->groupBy('status')->pluck('total','status')->all();
        $currentNodes=$db->table('jb_runs')->where('flow_id',$id)->selectRaw('node_id,count(*) as total')->groupBy('node_id')->pluck('total','node_id')->all();
        $arrivals=$db->table('jb_step_logs')->where('flow_id',$id)->selectRaw('to_node_id,count(*) as total')->groupBy('to_node_id')->pluck('total','to_node_id')->all();
        $logs=$db->table('jb_step_logs')->where('flow_id',$id)->orderByDesc('id')->limit(40)->get();
        $sms=$db->table('jb_sms_logs')->where('flow_id',$id)->orderByDesc('id')->limit(30)->get();
        $api=$db->table('jb_api_logs')->where('flow_id',$id)->orderByDesc('id')->limit(30)->get();
        $nodes=collect(json_decode($flow->graph_json,true)['nodes']??[])->keyBy('id');
        return response()->view('journey-builder::details',compact('flow','runs','runCounts','smsCounts','apiCounts','currentNodes','arrivals','logs','sms','api','nodes'))
            ->header('Cache-Control','no-store, private');
    }

    public function smsIndex(Request $request)
    {
        $v=$request->validate(['status'=>'nullable|in:sent,failed,unknown,sending','flow'=>'nullable|integer|min:1']);
        $db=$this->db();
        $q=$db->table('jb_sms_logs as s')->leftJoin('jb_flows as f','f.id','=','s.flow_id');
        if (!empty($v['status'])) TypedWhere::eq($q, 's.status', $v['status'], 20);
        if (!empty($v['flow'])) $q->where('s.flow_id',$v['flow']);
        $logs=$q->orderByDesc('s.id')->select(['s.*','f.title AS flow_title'])->paginate(60)->withQueryString();
        $flows=$db->table('jb_flows')->orderByDesc('id')->get(['id','title']);
        $counts=$db->table('jb_sms_logs')->selectRaw('status,count(*) AS total')->groupBy('status')->pluck('total','status')->all();
        return response()->view('journey-builder::sms',compact('logs','flows','counts'))->header('Cache-Control','no-store, private');
    }

    public function create(Request $request): JsonResponse
    {
        $v=$request->validate(['title'=>'required|string|max:200']);
        $db=$this->db();
        // Internal ASCII identifier is server-generated, opaque and not requested from admin.
        $code='jn_'.bin2hex(random_bytes(12));
        $id=$db->table('jb_flows')->insertGetId([
            'code'=>$code,'title'=>$v['title'],'status'=>'draft','revision'=>1,
            'graph_json'=>json_encode(Definition::blank(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
        ]);
        return response()->json(['id'=>$id,'code'=>$code,'url'=>route('jb.builder',$id)],201);
    }

    public function save(Request $request, int $id): JsonResponse
    {
        $v=$request->validate(['title'=>'required|string|max:200','graph'=>'required|array']);
        $graph=Definition::validate($v['graph'], false);
        $db=$this->db();
        $db->transaction(function () use ($db,$id,$v,$graph) {
            $flow=$db->table('jb_flows')->where('id',$id)->lockForUpdate()->first();
            abort_unless($flow,404);
            if ($flow->status!=='draft' || $db->table('jb_runs')->where('flow_id',$id)->exists()) {
                throw ValidationException::withMessages(['graph'=>'برای حفظ اجراها، فقط جرنی Draft بدون سابقه اجرا قابل ویرایش است.']);
            }
            $db->table('jb_flows')->where('id',$id)->update([
                'title'=>$v['title'], 'graph_json'=>json_encode($graph,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                'revision'=>$flow->revision+1, 'updated_at'=>now('UTC'),
            ]);
            // Purge credentials for removed API nodes or nodes switched away from manual-token mode.
            $secretIds = array_values(array_map(
                fn(array $node) => $node['id'],
                array_filter($graph['nodes'], fn(array $node) =>
                    $node['type'] === 'api' && ($node['config']['token_source'] ?? '') === 'secret')
            ));
            $oldTokens = $db->table('jb_api_credentials')->where('flow_id', $id);
            if ($secretIds) $oldTokens->whereNotIn('node_id', $secretIds);
            $oldTokens->delete();
        });
        return response()->json(['ok'=>true]);
    }

    // Write-only API token endpoint. A secret is scoped to exactly one flow and one API node.
    public function apiToken(Request $request, int $id, string $nodeId): JsonResponse
    {
        $input = $request->validate([
            'token' => 'required_without:clear|string|min:1|max:4096',
            'clear' => 'sometimes|boolean',
        ]);
        $clear = (bool)($input['clear'] ?? false);
        if (!$clear && preg_match('/[\x00-\x1f\x7f]/', (string)$input['token'])) {
            throw ValidationException::withMessages(['token'=>'توکن نباید شامل کاراکتر کنترلی یا خط جدید باشد.']);
        }
        $db = $this->db();
        $db->transaction(function () use ($db, $id, $nodeId, $input, $clear) {
            $flow = $db->table('jb_flows')->where('id', $id)->lockForUpdate()->first();
            abort_unless($flow, 404);
            if ($flow->status !== 'draft' || $db->table('jb_runs')->where('flow_id', $id)->exists()) {
                throw ValidationException::withMessages(['token'=>'توکن جرنی فعال یا دارای سابقه اجرا قابل تغییر نیست.']);
            }
            $nodes = json_decode($flow->graph_json, true, 512, JSON_THROW_ON_ERROR)['nodes'] ?? [];
            $node = collect($nodes)->first(fn($n) => $n['id'] === $nodeId && $n['type'] === 'api');
            abort_unless($node, 404);
            $store = TypedWhere::eq($db->table('jb_api_credentials')->where('flow_id', $id), 'node_id', $nodeId, 80);
            if ($clear) {
                $store->delete();
                return;
            }
            if (($node['config']['token_source'] ?? 'none') !== 'secret') {
                throw ValidationException::withMessages(['token'=>'ابتدا نوع احراز هویت را «توکن اختصاصی این گره» انتخاب و طرح را ذخیره کنید.']);
            }
            // Avoid updateOrInsert's implicit NVARCHAR parameter for a VARCHAR indexed node_id.
            $existingId = TypedWhere::eq($db->table('jb_api_credentials')->where('flow_id', $id), 'node_id', $nodeId, 80)->value('id');
            if ($existingId) $db->table('jb_api_credentials')->where('id',$existingId)->update([
                'encrypted_token'=>Crypt::encryptString($input['token']), 'updated_at'=>now('UTC'),
            ]);
            else $db->table('jb_api_credentials')->insert([
                'flow_id'=>$id,'node_id'=>$nodeId,'encrypted_token'=>Crypt::encryptString($input['token']),
                'created_at'=>now('UTC'),'updated_at'=>now('UTC'),
            ]);
        });
        return response()->json(['ok'=>true,'configured'=>!$clear])->header('Cache-Control','no-store, private');
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $v=$request->validate(['status'=>'required|in:active,paused']);
        $db=$this->db();
        $db->transaction(function () use ($db,$id,$v) {
            $flow=$db->table('jb_flows')->where('id',$id)->lockForUpdate()->first();
            abort_unless($flow,404);
            if ($flow->status==='draft' && $v['status']==='paused') {
                throw ValidationException::withMessages(['status'=>'جرنی پیش‌نویس قابل توقف نیست.']);
            }
            if ($v['status']==='active') {
                $graph=Definition::validate(json_decode($flow->graph_json,true),true);
                $types=array_column($graph['nodes'],'type');
                if (in_array('sms',$types,true) && (!config('journey_builder.sms.enabled') || !config('journey_builder.sms.url'))) {
                    throw ValidationException::withMessages(['status'=>'گره SMS وجود دارد، اما API پیامک هنوز فعال/تنظیم نشده است.']);
                }
                if (in_array('api',$types,true) && !config('journey_builder.api.enabled')) {
                    throw ValidationException::withMessages(['status'=>'ارسال API از تنظیمات سرور غیرفعال است.']);
                }
                // A manual secret must exist for every API node that requests one.
                $configured = $db->table('jb_api_credentials')->where('flow_id',$id)->pluck('node_id')->all();
                foreach ($graph['nodes'] as $node) {
                    if ($node['type'] === 'api' && ($node['config']['token_source'] ?? 'none') === 'secret'
                        && !in_array($node['id'], $configured, true)) {
                        throw ValidationException::withMessages(['status'=>'توکن گره API با شناسه '.$node['id'].' تنظیم نشده است.']);
                    }
                }
                if ($db->table('jb_runs')->where('flow_id',$id)->where('revision','!=',$flow->revision)->exists()) {
                    throw ValidationException::withMessages(['status'=>'نسخه تعریف جرنی با اجراهای قبلی مطابقت ندارد.']);
                }
            }
            $db->table('jb_flows')->where('id',$id)->update(['status'=>$v['status'],'updated_at'=>now('UTC')]);
        });
        return response()->json(['ok'=>true,'status'=>$v['status']]);
    }

    // Stream a per-flow CSV with bounded rows and spreadsheet-formula-injection protection.
    public function export(Request $request, int $id)
    {
        abort_unless($this->db()->table('jb_flows')->where('id',$id)->exists(),404);
        $v=$request->validate(['kind'=>'required|in:runs,sms,api,steps']);
        $kind=$v['kind'];
        $schemas=[
            'runs'=>['table'=>'jb_runs','fields'=>['id','user_id','node_id','state','next_run_at','completed_at','last_error','created_at']],
            'sms'=>['table'=>'jb_sms_logs','fields'=>['id','user_id','node_id','template','status','http_status','error_message','sent_at','created_at']],
            'api'=>['table'=>'jb_api_logs','fields'=>['id','user_id','node_id','method','target_host','status','http_status','error_message','sent_at','created_at']],
            'steps'=>['table'=>'jb_step_logs','fields'=>['id','user_id','from_node_id','to_node_id','port','created_at']],
        ];
        $schema=$schemas[$kind];
        $filename='journey-'.$id.'-'.$kind.'-'.gmdate('Ymd-His').'.csv';
        $db=$this->db();
        return response()->streamDownload(function () use ($db,$schema,$id) {
            $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,$schema['fields']);
            foreach ($db->table($schema['table'])->where('flow_id',$id)->orderBy('id')->limit(10000)->cursor() as $row) {
                $cells=[];
                foreach ($schema['fields'] as $field) {
                    $value=(string)($row->$field ?? '');
                    if (preg_match('/^\s*[=+\-@\t\r]/u',$value)) $value="'".$value;
                    $cells[]=$value;
                }
                fputcsv($out,$cells);
            }
            fclose($out);
        },$filename,['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }
}
