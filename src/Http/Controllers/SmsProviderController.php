<?php

namespace OpenJourney\LaravelJourneyBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenJourney\LaravelJourneyBuilder\Services\TypedWhere;

final class SmsProviderController extends Controller
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection','sqlsrv'));
    }

    public function index()
    {
        $providers = $this->db()->table('jb_sms_providers')->whereNull('deleted_at')->orderByDesc('is_default')->orderBy('id')->get();
        return response()->view('journey-builder::sms-providers', compact('providers'))->header('Cache-Control','private, no-store');
    }

    private function validateInput(Request $request, bool $create = false): array
    {
        return $request->validate([
            'name'=>'required|string|max:120',
            'sender'=>['required','string','max:24','regex:/^\+?[0-9]{3,23}$/D'],
            'receptor_attribute'=>['required','string','max:50','regex:/^[A-Za-z0-9_]{1,50}$/D'],
            'source_sign'=>'nullable|string|max:30',
            'api_key'=>[$create?'required':'nullable','string','max:2048','regex:/^[A-Za-z0-9_-]+$/D'],
            'is_enabled'=>'sometimes|boolean',
        ]);
    }

    public function store(Request $request)
    {
        $v = $this->validateInput($request,true);
        $db = $this->db();
        $db->transaction(function () use ($db,$v) {
            $now = now('UTC');
            $db->table('jb_sms_providers')->insert([
                'code'=>'sp_'.bin2hex(random_bytes(12)), 'name'=>$v['name'],
                'driver'=>'kavenegar', 'sender'=>$v['sender'],
                'receptor_attribute'=>$v['receptor_attribute'],
                'source_sign'=>$v['source_sign']??null,
                'encrypted_api_key'=>Crypt::encryptString($v['api_key']),
                'is_enabled'=>(bool)($v['is_enabled']??false), 'is_default'=>false,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        });
        return redirect()->route('jb.sms.providers')->with('message','پنل ثبت شد. برای شروع ارسال، آن را فعال و به‌عنوان پیش‌فرض انتخاب کنید.');
    }

    public function update(Request $request, int $id)
    {
        $v = $this->validateInput($request);
        $db = $this->db();
        $db->transaction(function () use ($db,$id,$v) {
            $old = $db->table('jb_sms_providers')->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($old,404);
            $enabled = (bool)($v['is_enabled']??false);
            if (!$enabled && (bool)$old->is_default) throw ValidationException::withMessages(['is_enabled'=>'ابتدا پنل پیش‌فرض دیگری انتخاب کنید.']);
            if (!$enabled && $this->hasUnresolved($id)) throw ValidationException::withMessages(['is_enabled'=>'پنل دارای پیامک آماده ارسال است و نمی‌تواند غیرفعال شود.']);
            $changes = [
                'name'=>$v['name'], 'sender'=>$v['sender'],
                'receptor_attribute'=>$v['receptor_attribute'], 'source_sign'=>$v['source_sign']??null,
                'is_enabled'=>$enabled,'updated_at'=>now('UTC'),
            ];
            if (!empty($v['api_key'])) $changes['encrypted_api_key']=Crypt::encryptString($v['api_key']);
            $db->table('jb_sms_providers')->where('id',$id)->update($changes);
        });
        return redirect()->route('jb.sms.providers')->with('message','تنظیمات پنل ذخیره شد.');
    }

    public function makeDefault(int $id)
    {
        $db = $this->db();
        $db->transaction(function () use ($db,$id) {
            // Serialize default changes by locking the flows table first (shared sequence).
            $db->table('jb_sms_providers')->orderBy('id')->lockForUpdate()->get(['id']);
            $provider=$db->table('jb_sms_providers')->where('id',$id)->whereNull('deleted_at')->first();
            abort_unless($provider,404);
            if (!$provider->is_enabled) throw ValidationException::withMessages(['provider'=>'پنل غیرفعال قابل انتخاب به‌عنوان پیش‌فرض نیست.']);
            $db->table('jb_sms_providers')->where('is_default',1)->update(['is_default'=>false,'updated_at'=>now('UTC')]);
            $db->table('jb_sms_providers')->where('id',$id)->update(['is_default'=>true,'updated_at'=>now('UTC')]);
        },3);
        return redirect()->route('jb.sms.providers')->with('message','پنل پیش‌فرض تغییر کرد.');
    }

    public function destroy(int $id)
    {
        $db = $this->db();
        $db->transaction(function () use ($db,$id) {
            $provider=$db->table('jb_sms_providers')->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_unless($provider,404);
            if ($this->hasUnresolved($id)) throw ValidationException::withMessages(['provider'=>'این پنل پیامک ارسال‌نشده دارد؛ ابتدا آن‌ها را تعیین تکلیف کنید.']);
            // A live flow can explicitly reference this panel, or implicitly use it as default.
            foreach (TypedWhere::oneOf($db->table('jb_flows'), 'status', ['active','paused'], 20)->get(['graph_json']) as $flow) {
                foreach (json_decode($flow->graph_json,true)['nodes']??[] as $node) {
                    if (($node['type']??'')!=='sms' || ($node['config']['delivery_mode']??'legacy')!=='managed') continue;
                    $selected=(int)($node['config']['provider_id']??0);
                    if ($selected===$id || ($provider->is_default && $selected===0)) {
                        throw ValidationException::withMessages(['provider'=>'این پنل در یک جرنی فعال/متوقف‌شده استفاده می‌شود؛ ابتدا جرنی را مدیریت کنید.']);
                    }
                }
            }
            // Soft-delete preserves provider identity in historical reports; encrypted key is destroyed.
            $db->table('jb_sms_providers')->where('id',$id)->update([
                'is_enabled'=>false,'is_default'=>false,'encrypted_api_key'=>Crypt::encryptString(bin2hex(random_bytes(32))),
                'deleted_at'=>now('UTC'),'updated_at'=>now('UTC'),
            ]);
        });
        return redirect()->route('jb.sms.providers')->with('message','پنل حذف شد و کلید API آن غیرقابل بازیابی شد.');
    }

    private function hasUnresolved(int $providerId): bool
    {
        return TypedWhere::oneOf($this->db()->table('jb_sms_logs')->where('provider_id',$providerId), 'status',
            ['pending','sending','retry_authorized'],20)->exists();
    }
}
