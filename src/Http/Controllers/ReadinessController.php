<?php

namespace OpenJourney\LaravelJourneyBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use OpenJourney\LaravelJourneyBuilder\Services\OnsiteMongoService;
use Throwable;

final class ReadinessController extends Controller
{
    // Do not expose connection strings, passwords, hostnames or raw exception messages.
    private function safeError(Throwable $e): string
    {
        $message = $e->getMessage();
        // Remove URI values even if a driver includes a database password in an exception.
        $message = preg_replace('~mongodb(?:\+srv)?://\S+~i', '[MongoDB URI redacted]', $message);
        $message = preg_replace('~(?i)(password|passwd|pwd|token|secret|api[_-]?key)\s*[:=]\s*[^\s,;]+~', '$1=[redacted]', $message);
        return mb_substr($message, 0, 260);
    }

    public function index(Request $request)
    {
        $checks = [];
        $connection = (string) config('journey_builder.connection','sqlsrv');
        $start = microtime(true);
        try {
            DB::connection($connection)->select('SELECT 1 AS health_check');
            $checks['mssql'] = ['status'=>'ok','label'=>'SQL Server','detail'=>'اتصال و اجرای کوئری آزمایشی موفق بود','elapsed_ms'=>(int)round((microtime(true)-$start)*1000)];
        } catch (Throwable $e) {
            $checks['mssql'] = ['status'=>'error','label'=>'SQL Server','detail'=>$this->safeError($e),'elapsed_ms'=>(int)round((microtime(true)-$start)*1000)];
        }

        $start=microtime(true);
        $uri=(string) config('journey_builder.onsite.mongo.uri','');
        if ($uri==='') {
            $checks['mongodb']=['status'=>'not_configured','label'=>'MongoDB','detail'=>'JOURNEY_BUILDER_MONGODB_URI تنظیم نشده است','elapsed_ms'=>0];
        } else {
            try {
                $db=app(OnsiteMongoService::class)->database();
                $db->command(['ping'=>1])->toArray();
                $collections=iterator_to_array($db->listCollectionNames(),false);
                $required=['onsites','users'];
                $missing=array_values(array_diff($required,$collections));
                $checks['mongodb']=[
                    'status'=>$missing?'warning':'ok','label'=>'MongoDB',
                    'detail'=>$missing?('اتصال برقرار است؛ Collectionهای یافت‌نشده: '.implode(', ', $missing)):'اتصال Ping موفق؛ Collectionهای Onsite و Users موجودند',
                    'elapsed_ms'=>(int)round((microtime(true)-$start)*1000),
                ];
            } catch (Throwable $e) {
                $checks['mongodb']=['status'=>'error','label'=>'MongoDB','detail'=>$this->safeError($e),'elapsed_ms'=>(int)round((microtime(true)-$start)*1000)];
            }
        }
        $ok=collect($checks)->every(fn($c)=>$c['status']==='ok');
        if ($request->boolean('json')) {
            return response()->json(['ready'=>$ok,'checks'=>$checks],$ok?200:503)
                ->header('Cache-Control','no-store, private');
        }
        return response()->view('journey-builder::readiness',compact('checks','ok'))
            ->header('Cache-Control','no-store, private');
    }
}
