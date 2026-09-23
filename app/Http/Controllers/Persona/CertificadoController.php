<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;

class CertificadoController extends Controller
{
    public function index()
    {
        return view('persona.certificado');
    }
}
