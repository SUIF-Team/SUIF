<?php

namespace App\Http\Controllers\Persona;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    public function index()
    {
        return view('persona.certificado');
    }
}
