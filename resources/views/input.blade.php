@php
$oldKey=preg_replace('/\[([^]]+)\]/', '.$1', $name);$v=old($oldKey,$value??'');$fieldId='field_'.($prefix??'').preg_replace('/[^a-zA-Z0-9_]/','_',$name);$type=$type??'text';$required=$required??false;
@endphp
<div class="field {{ $wide??false ? 'wide' : '' }}"><label for="{{ $fieldId }}">{{ $label }} @if($required)<span style="color:#bc9257">*</span>@endif</label>
@if(isset($choices))<select id="{{ $fieldId }}" name="{{ $name }}" @required($required)><option value="">— Pilih —</option>@foreach($choices as $key=>$text)<option value="{{ $key }}" @selected((string)$key===(string)$v)>{{ $text }}</option>@endforeach</select>
@elseif($type==='textarea')<textarea id="{{ $fieldId }}" name="{{ $name }}" rows="3" @required($required) maxlength="3000">{{ $v }}</textarea>
@else<input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" @if(!in_array($type,['password','file'])) value="{{ $v }}" @endif @required($required) @if($type==='file') accept="image/jpeg,image/png,application/pdf" @endif @if($type==='password') autocomplete="new-password" minlength="6" @endif @if($type==='number') min="0" @endif>@endif
@if($type==='file')<small>JPG, PNG, atau PDF. Maksimal 2 MB. Dokumen disimpan privat.</small>@endif
@if(isset($hint))<small>{{ $hint }}</small>@endif</div>
