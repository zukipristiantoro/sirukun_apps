<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Records {
 public static function definitions():array{return [
  'warga'=>['title'=>'Data warga','table'=>'warga','fields'=>['nama'=>'Nama lengkap|text','nik'=>'NIK (16 digit; bayi boleh kosong)|text','kartu_keluarga_id'=>'Kartu keluarga|kk','tanggal_lahir'=>'Tanggal lahir|date','jenis_kelamin'=>'Jenis kelamin|gender','hubungan_keluarga'=>'Hubungan dalam KK|hubungan','telepon'=>'Nomor ponsel|text','status'=>'Status warga|statuswarga'],'columns'=>['nama'=>'Nama warga','nik'=>'NIK','nomor_kk'=>'Nomor KK','rt_nama'=>'RT','status'=>'Status']],
  'keluarga'=>['title'=>'Kartu keluarga','table'=>'kartu_keluarga','fields'=>['nomor_kk'=>'Nomor kartu keluarga|text','hunian_id'=>'Hunian|hunian','status'=>'Status|statuskk'],'columns'=>['nomor_kk'=>'Nomor KK','alamat'=>'Alamat','rt_nama'=>'RT','kepala'=>'Kepala keluarga','status'=>'Status']],
  'hunian'=>['title'=>'Data hunian','table'=>'hunian','fields'=>['rt_id'=>'Wilayah RT|rt','alamat'=>'Alamat lengkap|textarea','jenis'=>'Jenis hunian|jenishunian','nama_pemilik'=>'Nama pemilik|text'],'columns'=>['alamat'=>'Alamat','rt_nama'=>'RT','jenis'=>'Jenis','nama_pemilik'=>'Pemilik']],
  'pengguna'=>['title'=>'Kelola pengguna','table'=>'pengguna','fields'=>['nama'=>'Nama lengkap|text','email'=>'Alamat email|email','password'=>'Kata sandi (minimal 6 karakter)|password','peran_id'=>'Peran pengguna|peran','jabatan'=>'Jabatan|jabatan','rt_id'=>'Wilayah RT (untuk peran RT)|rt','rw_id'=>'Wilayah RW (untuk peran RW)|rw','warga_id'=>'Warga terkait (untuk peran Warga)|warga','aktif'=>'Akun aktif|bool'],'columns'=>['nama'=>'Nama','email'=>'Email','peran_nama'=>'Peran','jabatan'=>'Jabatan','aktif'=>'Aktif']],
  'menu'=>['title'=>'Menu aplikasi','table'=>'menu','fields'=>['nama'=>'Nama menu|text','kode'=>'Kode menu|text','route'=>'Halaman tujuan|route','parent_id'=>'Menu induk (opsional)|parent','urutan'=>'Urutan menu|number','aktif'=>'Menu aktif|bool'],'columns'=>['nama'=>'Menu','kode'=>'Kode','route'=>'Halaman','urutan'=>'Urutan','aktif'=>'Aktif']],
  'rw'=>['title'=>'Wilayah RW','table'=>'rw','fields'=>['kode'=>'Kode RW|text','nama'=>'Nama lingkungan|text','kelurahan'=>'Kelurahan|text','kecamatan'=>'Kecamatan|text','kota'=>'Kota / kabupaten|text'],'columns'=>['kode'=>'Kode RW','nama'=>'Nama lingkungan','kelurahan'=>'Kelurahan','kecamatan'=>'Kecamatan','kota'=>'Kota']],
  'rt'=>['title'=>'Wilayah RT','table'=>'rt','fields'=>['rw_id'=>'Wilayah RW|rw','nomor'=>'Nomor RT|text'],'columns'=>['nomor'=>'Nomor RT','rw_nama'=>'RW']],
 ];}
 public static function def(string $kind):array{abort_unless(isset(self::definitions()[$kind]),404);return self::definitions()[$kind];}
 public static function query(string $kind){$d=self::def($kind);$table=$d['table'];$q=DB::table($table.' as x')->whereNull('x.deleted_at')->select('x.*');$u=auth()->user();
  if($kind==='warga'){$q->leftJoin('kartu_keluarga as k','k.id','=','x.kartu_keluarga_id')->leftJoin('hunian as h','h.id','=','k.hunian_id')->leftJoin('rt as r','r.id','=','h.rt_id')->addSelect('k.nomor_kk','h.rt_id','r.nomor as rt_nama');if(!$u->isAdmin())$q->whereIn('h.rt_id',$u->rtIds());}
  if($kind==='keluarga'){$q->join('hunian as h','h.id','=','x.hunian_id')->join('rt as r','r.id','=','h.rt_id')->addSelect('h.alamat','h.rt_id','r.nomor as rt_nama')->addSelect(DB::raw("(SELECT nama FROM warga WHERE kartu_keluarga_id=x.id AND hubungan_keluarga='kepala_keluarga' AND status='aktif' AND deleted_at IS NULL LIMIT 1) as kepala"));if(!$u->isAdmin())$q->whereIn('h.rt_id',$u->rtIds());if($u->roleCode()==='WARGA')$q->where('x.id',DB::table('warga')->where('id',$u->warga_id)->value('kartu_keluarga_id'));}
  if($kind==='hunian'){$q->join('rt as r','r.id','=','x.rt_id')->addSelect('r.nomor as rt_nama');if(!$u->isAdmin())$q->whereIn('x.rt_id',$u->rtIds());}
  if($kind==='pengguna')$q->join('peran as p','p.id','=','x.peran_id')->addSelect('p.nama as peran_nama');
  if($kind==='rt')$q->join('rw as r','r.id','=','x.rw_id')->addSelect('r.kode as rw_nama');
  return $q;
 }
 public static function options():array {
  $u=auth()->user();$ids=$u->rtIds();$rt=DB::table('rt as r')->join('rw as w','w.id','=','r.rw_id')->whereNull('r.deleted_at')->select('r.id',DB::raw("CONCAT('RT ',r.nomor,' / RW ',w.kode) as label"));
  if(DB::getDriverName()==='sqlite')$rt=DB::table('rt as r')->join('rw as w','w.id','=','r.rw_id')->whereNull('r.deleted_at')->select('r.id',DB::raw("'RT '||r.nomor||' / RW '||w.kode as label"));
  if(!$u->isAdmin())$rt->whereIn('r.id',$ids);
  $h=DB::table('hunian')->whereNull('deleted_at');if(!$u->isAdmin())$h->whereIn('rt_id',$ids);
  if($u->roleCode()==='WARGA'){$own=DB::table('warga')->find($u->warga_id);$h->where('id',DB::table('kartu_keluarga')->where('id',$own?->kartu_keluarga_id)->value('hunian_id'));}
  $hlist=$h->get();$k=DB::table('kartu_keluarga')->whereNull('deleted_at')->whereIn('hunian_id',$hlist->pluck('id'))->get();
  $w=DB::table('warga')->whereNull('deleted_at');if(!$u->isAdmin())$w->whereIn('kartu_keluarga_id',$k->pluck('id'));if($u->roleCode()==='WARGA')$w->where('id',$u->warga_id);
  return ['rt'=>$rt->pluck('label','id')->all(),'rw'=>DB::table('rw')->whereNull('deleted_at')->pluck('nama','id')->all(),'hunian'=>$hlist->pluck('alamat','id')->all(),'kk'=>$k->pluck('nomor_kk','id')->all(),'warga'=>$w->pluck('nama','id')->all(),'peran'=>DB::table('peran')->pluck('nama','id')->all(),'parent'=>DB::table('menu')->whereNull('deleted_at')->pluck('nama','id')->all(),'route'=>array_combine(array_keys(self::navigation()),array_values(self::navigation())),'gender'=>['L'=>'Laki-laki','P'=>'Perempuan'],'hubungan'=>['kepala_keluarga'=>'Kepala keluarga','istri'=>'Istri','suami'=>'Suami','anak'=>'Anak','lainnya'=>'Lainnya'],'statuswarga'=>['aktif'=>'Aktif','draf'=>'Draf','pindah'=>'Pindah','meninggal'=>'Meninggal'],'statuskk'=>['aktif'=>'Aktif','nonaktif'=>'Nonaktif'],'jenishunian'=>['rumah'=>'Rumah','kontrakan'=>'Kontrakan','kos'=>'Kos'],'bool'=>[1=>'Ya',0=>'Tidak'],'jabatan'=>['Ketua RT'=>'Ketua RT','Perangkat RT'=>'Perangkat RT','Ketua RW'=>'Ketua RW','Sekretaris RW'=>'Sekretaris RW']];
 }
 public static function navigation():array{return ['dashboard'=>'Dashboard','warga'=>'Data warga','keluarga'=>'Kartu keluarga','hunian'=>'Data hunian','mutasi'=>'Mutasi penduduk','tamu'=>'Tamu menginap','sewa'=>'Penghuni kontrakan / kos','surat'=>'Pelayanan surat','arsip'=>'Arsip surat','laporan'=>'Laporan bulanan','notifikasi'=>'Notifikasi','pengguna'=>'Kelola pengguna','wilayah'=>'Wilayah RT / RW','menu'=>'Menu aplikasi','log'=>'Log aktivitas','backup'=>'Pencadangan'];}
 public static function address(string $route):string{return match($route){'warga','keluarga','hunian','pengguna','menu'=>url('/data/'.$route),'wilayah'=>url('/data/rw'),'dashboard'=>url('/'),default=>url('/'.$route)};}
}
