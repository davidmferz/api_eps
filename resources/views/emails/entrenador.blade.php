<!DOCTYPE html>
<html>

<head>
    <title>AGENDA INBODY</title>
    <meta charset="UTF-8">
    <style type="text/css">
        html,
        body {
            background-color: whitesmoke !important;
        }
    </style>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
</head>

<body>
    <div class="container mt-4">
        <h3 class="text-center">
            ¡Te han agendado un socio!
        </h3>
        <div class="row  mt-5">
            <div class="col">
                Apreciable <b>{!! $datos->nombreEntrenador !!}.</b><br />
                Tu coordinador <b>{!! $datos->nombreCoordinador !!} </b> te a agendado un inbody:<br />
                el dia {!! $datos->fechaSolicitud_str !!} <br />
                en Sports World {!! $datos->nombreClub !!} <br />
                {!! $datos->hora !!}<br />
                <br />

                Se presentara contigo: <br />
                <b>{!! $datos->nombreSocio !!}</b>
                <br />
            </div>
        </div>
    </div>

    <footer>
        <!-- FOOTER -->
    </footer>
</body>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
    integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
    crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
    integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
    crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"
    integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"
    crossorigin="anonymous"></script>

</html>
