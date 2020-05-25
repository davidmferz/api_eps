@include('emails.header')
    <tr>
        <th colspan="4"> ¡Te han agendado un socio!</th>
    </tr>
    <tr>
        <td colspan="4" class="center">
            <p>
                Apreciable <b>{!! $datos->nombreEntrenador !!}.</b><br />
                Tu coordinador <b>{!! $datos->nombreCoordinador !!} </b> te a agendado un inbody:<br />
             
            </p>
        </td>
    </tr>
    <tr>
        <td>
            <p>
                el dia <b>{!! $datos->fechaSolicitud_str !!} </b><br />
                en Sports World {!! $datos->nombreClub !!} <br />
                {!! $datos->hora !!}<br />
                Se presentara contigo: <br />
                <b>{!! $datos->nombreSocio !!}</b>
            </p>
        </td>
    </tr>
    @include('emails.footer')
      