@extends('layouts.app')
@section('title',($record?'Edit':'Add').' '.$def['singular'])
@section('subtitle',$def['subtitle'])
@section('actions')<a href="/{{$module}}" class="button secondary">← Back to {{$def['title']}}</a>@endsection
@section('content')<section class="panel form-panel"><div class="panel-heading"><div><h2>{{ucfirst($def['singular'])}} details</h2><p>Fields marked * are required.</p></div></div><form method="post" action="/{{$module}}{{$record?'/'.$record->id:''}}" class="form-grid">@csrf @if($record)@method('PUT')@endif
@foreach($def['fields'] as $field=>[$label,$type,$rule])<div class="field {{$type==='textarea'?'span-2':''}}"><label for="{{$field}}">{{$label}}{{str_starts_with($rule,'required')?' *':''}}</label>
@if(isset($options[$type]))<select name="{{$field}}" id="{{$field}}" @required(str_starts_with($rule,'required'))><option value="">Select {{strtolower($label)}}</option>@foreach($options[$type] as $value=>$text)<option value="{{$value}}" @selected((string)old($field,$record->$field??'')===(string)$value)>{{$text}}</option>@endforeach</select>
@elseif($type==='textarea')<textarea name="{{$field}}" id="{{$field}}" rows="3" maxlength="3000">{{old($field,$record->$field??'')}}</textarea>
@else<input type="{{$type}}" name="{{$field}}" id="{{$field}}" value="{{old($field,$record->$field??($field==='duration'?60:($field==='reorder_level'?5:'')))}}" @if($type==='number') step="{{in_array($field,['duration','reorder_level'])?'1':'0.01'}}" min="0" @endif @required(str_starts_with($rule,'required'))>@endif
@error($field)<small class="field-error">{{$message}}</small>@enderror</div>@endforeach
@if($module==='products')<div class="info-note span-2">Opening stock and deliveries are recorded under <a href="/stock">Stock movements</a> after saving this product.</div>@endif
<div class="form-footer span-2"><a href="/{{$module}}" class="button secondary">Cancel</a><button class="button">Save {{$def['singular']}}</button></div></form></section>@endsection
