<?php

namespace OpenJourney\LaravelJourneyBuilder\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Credentials are never returned to Blade, JSON, logs or the saved flow graph. */
final class ChannelProviderController extends Controller
{
    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection(config('journey_builder.connection','sqlsrv'));
    }
    private function table(string $kind): string
    {
        abort_unless(in_array($kind,['email','webpush'],true),404);
        return $kind==='email'?'jb_email_providers':'jb_push_providers';
    }
    public function index(string $kind)
    {
        $table = $this->table($kind);
        $connection = config('journey_builder.connection', 'sqlsrv');
        $needsMigration = !\Illuminate\Support\Facades\Schema::connection($connection)->hasTable($table);
        $providers = $needsMigration
            ? collect()
            : $this->db()->table($table)->whereNull('deleted_at')
                ->orderByDesc('is_default')->orderBy('id')->get();

        return response()->view('journey-builder::channel-providers', compact('kind', 'providers', 'needsMigration'),
            $needsMigration ? 503 : 200)->header('Cache-Control', 'no-store, private');
    }
    private function validateInput(Request $request,string $kind,bool $create): array
    {
        $v=['name'=>'required|string|max:120','is_enabled'=>'sometimes|boolean'];
        if ($kind==='email') $v += [
            'smtp_host'=>['required','string','max:255','regex:/^[A-Za-z0-9.-]+$/D'],
            'smtp_port'=>'required|integer|in:25,465,587,2525',
            'smtp_username'=>'required|string|max:255',
            'smtp_password'=>($create?'required':'nullable').'|string|max:4096',
            'smtp_security'=>'required|in:starttls,smtps',
            'from_email'=>'required|email|max:254','from_name'=>'required|string|max:120',
            'max_per_minute'=>'required|integer|min:1|max:10000',
            'pop3_host'=>['nullable','string','max:255','regex:/^[A-Za-z0-9.-]+$/D'],
            'pop3_port'=>'nullable|integer|in:110,995',
            'pop3_username'=>'nullable|string|max:255',
            'pop3_password'=>'nullable|string|max:4096',
            'pop3_security'=>'nullable|in:tls,none',
        ];
        else $v += [
            'website_id'=>'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/D',
            'api_key'=>($create?'required':'nullable').'|string|max:4096',
            'auth_mode'=>'required|in:bearer,x-api-key,api-key',
        ];
        $input=$request->validate($v);
        if ($kind==='email') {
            if ($input['smtp_port']==465 && $input['smtp_security']!=='smtps') throw ValidationException::withMessages(['smtp_security'=>'پورت ۴۶۵ نیازمند SMTPS است.']);
            if ($input['smtp_port']!=465 && $input['smtp_security']!=='starttls') throw ValidationException::withMessages(['smtp_security'=>'این پورت نیازمند STARTTLS است.']);
            if (!empty($input['pop3_host']) && (empty($input['pop3_port']) || empty($input['pop3_username']) || ($create && empty($input['pop3_password']))))
                throw ValidationException::withMessages(['pop3_host'=>'برای POP3 میزبان، پورت و نام کاربری را کامل کنید.']);
        }
        return $input;
    }
    private function record(string $kind,array $v,bool $creating): array
    {
        $values=['name'=>$v['name'],'is_enabled'=>(bool)($v['is_enabled']??false),'updated_at'=>now('UTC')];
        if ($kind==='email') {
            foreach (['smtp_host','smtp_port','smtp_username','smtp_security','from_email','from_name','max_per_minute','pop3_host','pop3_port','pop3_username','pop3_security'] as $field)
                $values[$field]=$v[$field]??null;
            if (!empty($v['smtp_password'])) $values['smtp_secret']=Crypt::encryptString($v['smtp_password']);
            if (!empty($v['pop3_password'])) $values['pop3_secret']=Crypt::encryptString($v['pop3_password']);
        } else {
            foreach (['website_id','auth_mode'] as $field) $values[$field]=$v[$field];
            if (!empty($v['api_key'])) $values['api_secret']=Crypt::encryptString($v['api_key']);
        }
        if ($creating) $values += ['is_default'=>false,'created_at'=>now('UTC')];
        return $values;
    }
    public function store(Request $request,string $kind)
    {
        $table=$this->table($kind); $v=$this->validateInput($request,$kind,true);
        $this->db()->table($table)->insert($this->record($kind,$v,true));
        return redirect()->route('jb.channels.index',$kind)->with('message','سرویس ثبت شد. پس از تست، آن را پیش‌فرض کنید.');
    }
    public function update(Request $request,string $kind,int $id)
    {
        $table=$this->table($kind); $v=$this->validateInput($request,$kind,false);$db=$this->db();
        $db->transaction(function()use($db,$table,$v,$id,$kind){
            $old=$db->table($table)->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();abort_unless($old,404);
            if ($old->is_default && empty($v['is_enabled'])) throw ValidationException::withMessages(['is_enabled'=>'ابتدا سرویس پیش‌فرض دیگری انتخاب کنید.']);
            if (empty($v['is_enabled']) && $this->hasQueued($kind,$id)) throw ValidationException::withMessages(['is_enabled'=>'سرویس دارای ارسال در صف است.']);
            $db->table($table)->where('id',$id)->update($this->record($kind,$v,false));
        });
        return redirect()->route('jb.channels.index',$kind)->with('message','تنظیمات ذخیره شد.');
    }
    public function makeDefault(string $kind,int $id)
    {
        $table=$this->table($kind);$db=$this->db();
        $db->transaction(function()use($db,$table,$id){
            $db->table($table)->orderBy('id')->lockForUpdate()->get(['id']);
            $p=$db->table($table)->where('id',$id)->where('is_enabled',1)->whereNull('deleted_at')->first();abort_unless($p,404);
            $db->table($table)->where('is_default',1)->update(['is_default'=>false,'updated_at'=>now('UTC')]);
            $db->table($table)->where('id',$id)->update(['is_default'=>true,'updated_at'=>now('UTC')]);
        },3);
        return redirect()->route('jb.channels.index',$kind)->with('message','سرویس پیش‌فرض تغییر کرد.');
    }
    public function destroy(string $kind,int $id)
    {
        $table=$this->table($kind);$db=$this->db();
        $db->transaction(function()use($db,$table,$id,$kind){
            $p=$db->table($table)->where('id',$id)->whereNull('deleted_at')->lockForUpdate()->first();abort_unless($p,404);
            if ($p->is_default || $this->hasQueued($kind,$id)) throw ValidationException::withMessages(['provider'=>'سرویس پیش‌فرض یا دارای ارسال در صف قابل حذف نیست.']);
            foreach ($db->table('jb_flows')->whereIn('status',['active','paused'])->get(['graph_json']) as $f)
                foreach ((json_decode($f->graph_json,true)['nodes']??[]) as $n)
                    if (($n['type']??'')===$kind && (int)($n['config']['provider_id']??0)===$id)
                        throw ValidationException::withMessages(['provider'=>'این سرویس در یک جرنی فعال یا غیرفعال استفاده شده است.']);
            $values=['is_enabled'=>false,'is_default'=>false,'deleted_at'=>now('UTC'),'updated_at'=>now('UTC')];
            if ($kind==='email'){$values['smtp_secret']=Crypt::encryptString(bin2hex(random_bytes(20)));$values['pop3_secret']=null;}
            else $values['api_secret']=Crypt::encryptString(bin2hex(random_bytes(20)));
            $db->table($table)->where('id',$id)->update($values);
        });
        return redirect()->route('jb.channels.index',$kind)->with('message','سرویس حذف منطقی شد و رمز آن دیگر قابل بازیابی نیست.');
    }
    private function hasQueued(string $kind,int $id): bool
    {
        return $this->db()->table('jb_channel_dispatches')->where('channel',$kind)->where('provider_id',$id)
            ->whereIn('status',['pending','sending','retry_authorized'])->exists();
    }
}
