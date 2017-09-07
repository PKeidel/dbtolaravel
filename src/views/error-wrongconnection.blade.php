@extends('dbtolaravel::layout')

@section('content')
    <h4>Error</h4>
    <p>{{ $message }}</p>
    <br><br>
    <form>
        <select name="connection">
            @foreach($connections as $c)
                <option>{{ $c }}</option>
            @endforeach
        </select>
        <input type="submit" value="Set Connection">
    </form>
@endsection