<?php

namespace App\Http\Controllers\LibraryPanel;

use Auth;
use Input;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminUsers\AdminUser;

class AuthController extends Controller
{
    public function login() 
    {   
        if (Auth::guard("library")->check())
        {
            return redirect()->route('library.home');
        }  
        return view('LibraryPanel.Auth.login');
    }

    public function postLogin(Request $request)
    {
        $email = $request->input('email');
        $pass = $request->input('password');

        $user = AdminUser::where('email',$email)->first();

        if (!$user) {
            session()->flash('messages', 'error|No Existe un usuario con ese correo');
            return redirect()->back()->withInput();
        }

        if ($user->area_id == 3 || $user->area_id == 4) {       

            if (Auth::guard('library')->attempt(['email' => $email, 'password' => $pass],$request->get('remember-me', 0)))
            {
                return redirect()->route('library.home');
            }

            session()->flash('messages', 'error|El password es incorrecto');
            return redirect()->back()->withInput();
            
        } else {
            
            if ($user->area_id == 2) {
                return redirect()->route("finance.login")->withInput();
            } else {
                return redirect()->route("computo.login")->withInput();
            }
        }
    }

    public function logout(Request $request)
    {
        Auth::guard('library')->logout();
        session()->flush();
        return redirect('/library');
    }
}