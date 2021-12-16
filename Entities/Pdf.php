<?php
namespace PDFReport\Entities;

use DateTime;
use MapasCulturais\App;
use Doctrine\ORM\Mapping as ORM;
use MapasCulturais\RegistrationMeta;

class Pdf extends \MapasCulturais\Entity{

    static public  function getValueField($id, $registration) {
        $app = App::i();
        $body = 'field_'.$id;
        return $app->repo('RegistrationMeta')->findBy([
            'key' => $body,
            'owner' => $registration
        ]);
              
    }

    static public function getNameField($id) {
        $app = App::i();
        $body = 'field_'.$id;
        return $app->repo('RegistrationFieldConfiguration')->findBy(['owner' => $id]);
    }

    static public function mask($val, $mask) {
        $maskared = '';
        $k = 0;
        for($i = 0; $i<=strlen($mask)-1; $i++) {
            if($mask[$i] == '#') {
                if(isset($val[$k])) $maskared .= $val[$k++];
            } else {
                if(isset($mask[$i])) $maskared .= $mask[$i];
            }
        }
        return $maskared;
    }

    static function oportunityRegistrationAproved($idopportunity, $status) {

        $app = App::i();
        $opp = $app->repo('Opportunity')->find($idopportunity);
        
        if($status == 10) {
            $dql = "SELECT r
                    FROM 
                    MapasCulturais\Entities\Registration r
                    WHERE r.opportunity = {$idopportunity}
                    AND r.status = 10 ORDER BY r.consolidatedResult DESC";
            $query = $app->em->createQuery($dql);
            $regs = $query->getResult();
        }else{
            $regs = $app->repo('Registration')->findBy(
                [
                'opportunity' => $idopportunity
                ]
            );
        }
        
        return ['opp' => $opp, 'regs' => $regs];
    }

    static function oportunityAllRegistration($idopportunity, $orderBy = null){
        $app = App::i();
        $opp = $app->repo('Opportunity')->find($idopportunity);

        $regs = $app->repo('Registration')->findBy(
            [
            'opportunity' => $idopportunity
            ]
        );

        if($orderBy != null){
            $type = $opp->evaluationMethodConfiguration->type->id;
            if($orderBy == 'note'){
                if($type == 'technicalna'){
                    dump("nota do nao se aplica");
                    die;
                }
                usort($regs, function ($item1, $item2) {
                    if ($item1->consolidatedResult == $item2->consolidatedResult) return 0;
                    return ($item1->consolidatedResult < $item2->consolidatedResult) ? 1 : -1;
                });
            }else if($orderBy == 'alfa'){
                usort($regs, function ($a, $b) {
                    return strcmp($a->owner->name, $b->owner->name);
                });
            }
        }
        return ['opp' => $opp, 'regs' => $regs];
    }

    static function verifyResource($idOportunidade) {
        $app = App::i();
        $opp = $app->repo('OpportunityMeta')->findBy(['owner'=>$idOportunidade,'key'=>'claimDisabled']);
        return $opp;
    }

    static function handleRedirect($error_message, $status_code, $opp_id){
        $app = App::i();
        $_SESSION['error'] = $error_message;
        $url = $app->createUrl('oportunidade/'.$opp_id.'#/tab=inscritos');
        $app->redirect(substr_replace($url ,"", -1), $status_code);
    }

    static function listSubscribedHandle($app, $array, $getData){
        $array['regs'] = self::oportunityRegistrationAproved($getData['idopportunityReport'], 'ALL');
        if(empty($array['regs']['regs'])){
            self::handleRedirect('Ops! Não tem inscrito nessa oportunidade.', 401, $getData['idopportunityReport']);
        }
        $array['title'] = 'Relatório de inscritos na oportunidade';
        $array['template'] = 'pdf/subscribers';
        return $array;
    }

    static function listPreliminaryHandle($app, $array, $getData){

        $array['regs'] = self::oportunityAllRegistration($getData['idopportunityReport'], $getData['typeRelatorio'] ?? null);
        if(empty($array['regs']['regs'])){
            self::handleRedirect('Ops! A oportunidade deve estar publicada.', 401, $getData['idopportunityReport']);
        }

        $verifyResource = self::verifyResource($getData['idopportunityReport']);

        if(isset($verifyResource[0])){
            $array['claimDisabled'] = $verifyResource[0]->value;
        }
        $array['title'] = 'Resultado Preliminar do Certame';
        $array['template'] = 'pdf/preliminary';
        return $array;
    }

    static function listDefinitiveHandle($app, $array, $period = false, $getData){
        $id = $getData['idopportunityReport'];

        $dqlOpMeta = "SELECT op FROM 
            MapasCulturais\Entities\OpportunityMeta op
            WHERE op.owner = {$id}";

        $resultOpMeta = $app->em->createQuery($dqlOpMeta)->getResult();

        $dateInit = $dateEnd = $hourInit = $hourEnd = "";

        foreach ($resultOpMeta as $key => $valueOpMeta) {
            if($valueOpMeta->key == 'date-initial'){
                $dateInit = $valueOpMeta->value;
            }
            if($valueOpMeta->key == 'hour-initial'){
                $hourInit = $valueOpMeta->value;
            }
            if($valueOpMeta->key == 'date-final'){
                $dateEnd = $valueOpMeta->value;
            }
            if($valueOpMeta->key == 'hour-final'){
                $hourEnd = $valueOpMeta->value;
            }
        }
        $dateHourNow = new DateTime;
        
        $dateAndHourInit = $dateInit.' '.$hourInit;

        $dateVerifyPeriod = DateTime::createFromFormat('d/m/Y H:i:s', $dateAndHourInit);

        if($dateHourNow > $dateVerifyPeriod){
            $period = true;
        }

        if($period) {
            $array['regs'] = self::oportunityRegistrationAproved($getData['idopportunityReport'], 10);
            if(empty($array['regs']['regs'])){
                self::handleRedirect('Ops! Para gerar o relatório definitivo a oportunidade deve estar publicada.', 401, $getData['idopportunityReport']);
            }
            
            //SELECT AOS RECURSOS
            $dql = "SELECT r
            FROM 
            Saude\Entities\Resources r
            WHERE r.opportunityId = {$id}";
            $resource = $app->em->createQuery($dql)->getResult();
            $countPublish = 0;//INICIANDO VARIAVEL COM 0
            foreach ($resource as $key => $value) {
                if($value->replyPublish == 1 && $value->opportunityId->publishedRegistrations == 1) {
                    $countPublish++;//SE ENTRAR INCREMENTA A VARIAVEL
                }
            }
            if($countPublish == count($resource) && $countPublish > 0 && count($resource) > 0) {
                $array['regs'] = self::oportunityRegistrationAproved($getData['idopportunityReport'], 10);
                $array['title'] = 'Resultado Definitivo do Certame';
                $array['template'] = 'pdf/definitive';
               
            }else if($countPublish == count($resource) && $countPublish == 0 && count($resource) == 0){
               
                $array['regs'] = self::oportunityRegistrationAproved($getData['idopportunityReport'], 10);
                
                if(empty($array['regs']['regs'])) {
                    self::handleRedirect('Ops! Você deve publicar a oportunidade para esse relatório.', 401, $getData['idopportunityReport']);
                }

                $verifyResource = self::verifyResource($getData['idopportunityReport']);
                
                if(isset($verifyResource[0])){
                    $array['claimDisabled'] = $verifyResource[0]->value;
                }
                
                if(isset($regs['regs'][0]) && empty($verifyResource) || $array['claimDisabled'] == 1 ){
                    $array['title'] = 'Resultado Definitivo do Certame';
                    $array['template'] = 'pdf/definitive';
                }else if(isset($regs['regs'][0]) && empty($verifyResource) || $array['claimDisabled'] == 0){
                    $array['title'] = 'Resultado Definitivo do Certame';
                    $array['template'] = 'pdf/definitive';
                }else{
                    $app->redirect($app->createUrl('oportunidade/'.$getData['idopportunityReport'].'#/tab=inscritos'), 401);
                }
            }else{
                $array['regs'] = self::oportunityRegistrationAproved($getData['idopportunityReport'], 10);
                $array['title'] = 'Resultado Definitivo do Certame';
                $array['template'] = 'pdf/definitive';
            }
        }else{
            self::handleRedirect('Ops! Ocorreu um erro inesperado.', 401, $getData['idopportunityReport']);
        }
        return $array;
    }
    
    static function listContactsHandle($app, $array, $getData){
        $array['regs'] = self::oportunityRegistrationAproved($getData['idopportunityReport'], 10);

        if(empty($regs['regs']['regs'])){
            self::handleRedirect('', 401, $getData['idopportunityReport']);
        }
        $array['title'] = 'Relatório de contato';
        $array['template'] = 'pdf/contact';
        return $array;
    }

    static  function getSectionNote($opp, $registration, $section_id){
        $total = 0.00;
        $app = App::i();
        $committee = $opp->getEvaluationCommittee();
        $type = $opp->evaluationMethodConfiguration->type->id;
        $users = [];
        foreach ($committee as $item) {
            $users[] = $item->agent->user->id;
        }
        $evaluations = $app->repo('RegistrationEvaluation')->findByRegistrationAndUsersAndStatus($registration, $users);
        foreach ($evaluations as $eval){
            $cfg = $eval->getEvaluationMethodConfiguration();
            $category = $eval->registration->category;
            $totalSection = 0.00;
            $totalWeight = 0.00;
            foreach ($cfg->criteria as $cri) {
                if ($section_id == $cri->sid) {
                    $key = $cri->id;
                    if(!isset($eval->evaluationData->$key)){
                        return null;
                    } else if($type == 'technicalna') {
                        $val = floatval($eval->evaluationData->$key);
                        if($val != ''){
                            $totalSection += is_numeric($val) ? floatval($cri->weight) * floatval($val) : 0;
                            $totalWeight += $cri->weight;
                        }
                    }
                    else{
                        $val = floatval($eval->evaluationData->$key);
                        $totalSection += is_numeric($val) ? floatval($cri->weight) * floatval($val) : 0;
                    }
                }
            }
            $total += $type != 'technicalna' ? floatval($totalSection) : floatval($totalSection/$totalWeight);
        }
        return $total / count($users);
    }
}