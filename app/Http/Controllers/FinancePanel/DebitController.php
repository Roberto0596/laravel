<?php

namespace App\Http\Controllers\FinancePanel;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Alumns\Debit;
use App\Models\Alumns\User;
use Input;
use Auth;

class DebitController extends Controller
{
    public function index()
	{
		return view('FinancePanel.debit.index');
    }

    //le agregamos los botones a la tabla y los datos
    public function showDebit(Request $request)
    {
        $current_user = Auth::guard("finance")->user();      
        $res = [ "data" => []];

        //metemos a la sesion el modo en el que estaba
        if (session()->has('mode')) 
        {
            session()->forget('mode');
        }
        session(["mode"=>$request->input('mode')]);

        $debits = Debit::where([["status","=",$request->input('mode')],["period_id","=",$request->input('period')]])->get();     

        foreach($debits as $key => $value)
        {
            if ($value->id_alumno != null) {
                $alumn = getDataAlumnDebit($value->id_alumno);
                array_push($res["data"],[
                    "#" => (count($debits)-($key+1)+1),
                    "Alumno" =>$alumn["Nombre"]." ".$alumn["ApellidoPrimero"]." ".$alumn["ApellidoSegundo"],
                    "Email" =>strtolower($alumn["Email"]),
                    "Descripción" => $value->description,
                    "Importe" => "$".number_format($value->amount,2),
                    "Matricula" =>$alumn["Matricula"],
                    "Estado" =>($value->status==1)?"Pagada":"Pendiente",
                    "Fecha" => substr($value->created_at,0,11),
                    "Carrera" =>$alumn['nombreCarrera'],
                    "Localidad" =>$alumn["Localidad"].", ".$alumn['nombreEstado'],
                    "method" => $value->payment_method,
                    "debitId" => $value->id,
                    "id_order" => $value->id_order              
                ]);
            }
        }
        return response()->json($res);
    }

    //este metodo lo usamos con ajax para cargar los datos del adeudo para despues pasarlos al modal
	public function seeDebit(Request $request) 
	{
       
        $debit = Debit::find($request->input("DebitId"));
        $alumn = selectTable("users", "id_alumno",$debit->id_alumno,"si");
        $data = array(
            "concept"   =>getDebitType($debit->debit_type_id)->name,
            "alumnName" =>$alumn->name." ".$alumn->lastname,
            'description'=>$debit->description,
            "amount"    =>$debit->amount,
            "debitId"   => $debit->id,
            "alumnId" => $alumn->id_alumno,
            "status"    => $debit->status
                    
        );
        return response()->json($data);
    }

   
    // sirve para editar un adeudo 
    public function update(Request $request)
    {
        try 
        {
            $array = $request->input();
            $debit = Debit::find($request->input("DebitId"));

            if (array_key_exists("EditStatus", $array))
            {
                $debit->id_alumno = $request->input("EditId_alumno");
                $debit->status = $request->input("EditStatus");
                $debit->amount = $request->input("EditAmount");

                if ($debit->debit_type_id == 1 && $debit->status == 1) 
                {
                    $alumn = User::where("id_alumno","=",$debit->id_alumno)->first();
                    $enrollement = realizarInscripcion($alumn->id_alumno);
                    if ($enrollement!=false)
                    {
                        if ($enrollement!="reinscripcion") 
                        {
                            updateByIdAlumn($alumn->id_alumno,"Matricula",$enrollement);
                            // $alumn->email = "a".str_replace("-","",$enrollement)."@unisierra.edu.mx"; 
                        }
                        $alumn->inscripcion=3;
                        $alumn->save();
                        addNotify("Pago de colegiatura",$alumn->id,"alumn.charge");
                        //generamos los documentos de inscripcion
                        insertInscriptionDocuments($alumn->id);
                    }
                    else
                    {
                        session()->flash("messages","error|No pudimos guardar los datos");
                        return redirect()->back();
                    }
                }
            }

            $debit->description = $request->input("EditDescription");
            $debit->save();
            session()->flash("messages","success|Se guardó correctamente");
            return redirect()->back();
        } 
        catch (\Exception $th) 
        {
           dd($th);
            session()->flash("messages","error|No pudimos guardar los datos");
            return redirect()->back();
        }        
    }

    //creamos un nuevo adeudos y se guarda el taba de debits
    public function save(Request $request) 
    {
        $request->validate([
            'description' => 'required',
            'debit_type_id'=>'required',
            'amount' => 'required',
            'id_alumno'=>'required',
        ]);

        try 
        {
            $debit = new Debit();
            $debit->debit_type_id = $request->input("debit_type_id");
            $debit->amount = $request->input("amount");
            $debit->description = $request->input("description");
            $debit->id_alumno = $request->input("id_alumno");
            $debit->admin_id = Auth::guard("finance")->user()->id;
            $debit->save();
            session()->flash("messages","success|El alumno tiene un nuevo adeudo");
            return redirect()->back();
        } 
        catch (\Exception $th) 
        {
            session()->flash("messages","error|No pudimos guardar los datos");
            return redirect()->back();
        }
    }


    // accedemos a este método con ajax para cargar los datos de la orden 
    public function showPayementDetails(Request $request)
    {       
        $debit = Debit::find($request->input("DebitId"));
        require_once("conekta/Conekta.php");
        \Conekta\Conekta::setApiKey("key_b6GSXASrcJATTGjgSNxWFg");
        \Conekta\Conekta::setApiVersion("2.0.0");
        $order = \Conekta\Order::find($debit->id_order);
        if ($request->input('is')=="card")
        {
            $data = array(
            "id"   => $order->id,
                "paymentMethod" => "Tarjeta",
                "amount"        =>  "$". $order->amount/100 . $order->currency,
                "type"=>"card"                   
            );
        }
        else if($request->input('is')=="spei")
        {
            $data = array(
                "id"   => $order->id,
                "paymentMethod" => "SPEI",
                "reference"     => $order->charges[0]->payment_method->receiving_account_number,
                "amount"        => "$". $order->amount/100 . $order->currency,
                "type"=> "spei"                  
            );           
        }
        else
        {
            $data = array(
                "id"   => $order->id,
                "paymentMethod" => $order->charges[0]->payment_method->service_name,
                "reference"     => $order->charges[0]->payment_method->reference,
                "amount"        => "$".$order->amount/100 ." ". $order->currency,
                "type"=> "nocard"                  
            );  
        }
        return response()->json($data);
    }

    public function delete($id)
    {
        try{
            Debit::destroy($id);
            session()->flash("messages","success|Se borro el adeudo con exito");
            return redirect()->back();
        } catch(\Exception $e) {
            session()->flash("messages","error|No se pudo eliminar el adeudo");
            return redirect()->back();
        }
    }    
}
