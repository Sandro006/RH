<?php

namespace App\Controllers;

use App\Libraries\Common\SessionUtilisateur;

class Home extends BaseController
{
    public function index(): \CodeIgniter\HTTP\ResponseInterface
    {
        return service('response')->redirect(SessionUtilisateur::courante() === null ? '/login' : '/dashboard');
    }
}
