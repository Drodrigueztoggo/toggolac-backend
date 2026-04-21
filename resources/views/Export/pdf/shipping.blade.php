<!DOCTYPE html>
<html lang="es">

<head>
    <title>Modelado de imagen en HTML</title>
</head>
<style>
    *{
           font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif
       }
   </style>
<body>
    <header style="text-align: center;">
        <img src="https://toggolac.com/wp-content/uploads/2023/11/toggopdf.png" alt="Logo de Togo">
    </header>
    <main>
        <h1 style="text-align: center;">!Thank you for believing in us!</h1>
        <div style="width: 100%; text-align: center;">
            <table style="min-width: 700px;margin: auto;text-align: left;">
                <tr>
                    <td>
                        <table>
                            <tr>
                                <th colspan="2" style="text-align: center;">
                                    Origin
                                </th>
                            </tr>
                            <tr>
                                <td>Address:</td>
                                <td>{{ $originaddress }}</td>
                            </tr>
                            <tr>
                                <td>City:</td>
                                <td>{{ $origincity }}</td>
                            </tr>
                            <tr>
                                <td>State:</td>
                                <td>{{ $originstate }}</td>
                            </tr>
                            <tr>
                                <td>Zip Code:</td>
                                <td>{{ $originzipcode }}</td>
                            </tr>
                            <tr>
                                <td>Country:</td>
                                <td>{{ $origincountry }}</td>
                            </tr>
                            <tr>
                                <td>Date:</td>
                                <td>{{ $origindate }}</td>
                            </tr>
                            <tr>
                                <td>Weight:</td>
                                <td>{{ $originweight }}</td>
                            </tr>
                        </table>
                    </td>
                    <td>
                        <table>
                            <tr>
                                <th colspan="2" style="text-align: center;">
                                    Destination
                                </th>
                            </tr>
                            <tr>
                                <td>Address:</td>
                                <td>{{ $destinationaddress }}</td>
                            </tr>
                            <tr>
                                <td>City:</td>
                                <td>{{ $destinationcity }}</td>
                            </tr>
                            <tr>
                                <td>State:</td>
                                <td>{{ $destinationstate }}</td>
                            </tr>
                            <tr>
                                <td>Zip Code:</td>
                                <td>{{ $destinationzipcode }}</td>
                            </tr>
                            <tr>
                                <td>Country:</td>
                                <td>{{ $destinationcountry }}</td>
                            </tr>
                            <tr>
                                <td>Name:</td>
                                <td>{{ $destinationname }}</td>
                            </tr>
                           
                        </table>
                    </td>
                </tr>
            </table>
        </div>

    </main>

</body>

</html>