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

    static function oportunityRegistrationByStatus($idopportunity, $status = 0) {
        $app = App::i();
        $opp = $app->repo('Opportunity')->find($idopportunity);
        
        $where = "";
        if ($status) {
            $where .= " AND r.status = :status ";
        } else {
            $where .= " AND r.status > :status ";
        }

        $dql = "SELECT r
                FROM 
                MapasCulturais\Entities\Registration r
                WHERE r.opportunity = :opportunity
                {$where}
                ORDER BY r.consolidatedResult DESC";
        $query = $app->em->createQuery($dql);
        $query->setParameter('opportunity', $idopportunity);

        $query->setParameter('status', $status);
        $regs = $query->getResult();
       
        return ['opp' => $opp, 'regs' => $regs];
    }

    static function oportunityAllRegistration($idopportunity){
        $app = App::i();
        $opp = $app->repo('Opportunity')->find($idopportunity);

        $regs = $app->repo('Registration')->findBy(
            [
            'opportunity' => $idopportunity
            ]
        );
        
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
        $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport']);
        if(empty($array['regs']['regs'])){
            self::handleRedirect('Ops! Não tem inscrito nessa oportunidade.', 401, $getData['idopportunityReport']);
        }
        $array['title'] = 'Relatório de inscritos na oportunidade';
        $array['template'] = 'pdf/subscribers';
        return $array;
    }

    static function listPreliminaryHandle($app, $array, $getData){

        $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport'], 10);
        
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
            WHERE op.owner = :owner";

        $query = $app->em->createQuery($dqlOpMeta);
        $query->setParameter('owner', $id);

        $resultOpMeta = $query->getResult();

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
            $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport'], 10);
            if(empty($array['regs']['regs'])){
                self::handleRedirect('Ops! Para gerar o relatório definitivo a oportunidade deve estar publicada.', 401, $getData['idopportunityReport']);
            }
            
            //SELECT AOS RECURSOS
            $dql = "SELECT r
                FROM 
                Saude\Entities\Resources r
                WHERE r.opportunityId = :opportunity";
            $query = $app->em->createQuery($dql);
            $query->setParameter('opportunity', $id);

            $resource = $query->getResult();

            $countPublish = 0;//INICIANDO VARIAVEL COM 0
            foreach ($resource as $key => $value) {
                if($value->replyPublish == 1 && $value->opportunityId->publishedRegistrations == 1) {
                    $countPublish++;//SE ENTRAR INCREMENTA A VARIAVEL
                }
            }
            if($countPublish == count($resource) && $countPublish > 0 && count($resource) > 0) {
                $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport'], 10);
                $array['title'] = 'Resultado Definitivo do Certame';
                $array['template'] = 'pdf/definitive';
               
            }else if($countPublish == count($resource) && $countPublish == 0 && count($resource) == 0){
               
                $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport'], 10);
                
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
                $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport'], 10);
                $array['title'] = 'Resultado Definitivo do Certame';
                $array['template'] = 'pdf/definitive';
            }
        }else{
            self::handleRedirect('Ops! Ocorreu um erro inesperado.', 401, $getData['idopportunityReport']);
        }
        return $array;
    }
    
    static function listContactsHandle($app, $array, $getData){
        $array['regs'] = self::oportunityRegistrationByStatus($getData['idopportunityReport'], 10);

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
        $users = [];
        foreach ($committee as $item) {
            $users[] = $item->agent->user->id;
        }
        //AS INSCRIÇÕES AVALIADAS E ENVIADAS
        $status = [ 
            \MapasCulturais\Entities\RegistrationEvaluation::STATUS_EVALUATED,
            \MapasCulturais\Entities\RegistrationEvaluation::STATUS_SENT
        ];
        $evaluations = $app->repo('RegistrationEvaluation')->findByRegistrationAndUsersAndStatus($registration, $users, $status);
        foreach ($evaluations as $eval){
            $cfg = $eval->getEvaluationMethodConfiguration();
            $category = $eval->registration->category;
            $totalSection = 0.00;
            foreach ($cfg->criteria as $cri) {
                //dump($cri);
                if ($section_id == $cri->sid) {
                    $key = $cri->id;
                    if(!isset($eval->evaluationData->$key)){
                        return null;
                    } else {
                        $val = floatval($eval->evaluationData->$key);
                        $totalSection += is_numeric($val) ? floatval($cri->weight) * floatval($val) : 0;
                    }
                }
            }
            $total += floatval($totalSection);
        }
        
        //TOTAL DE AVALIAÇÕES
        $num = count($evaluations);
        //SE TIVER UMA OU MAIS AVALIAÇÃO
        if($num > 0) {
            //NOTA DA AVALIAÇÃO DIVIDIDA PELO TOTAL DE AVALIAÇÃO
            return  number_format($total / $num, 2);
        } else {
            return null;
        }
        
    }
    static function getResultsByOrder($type, $sub, $opp, $sections, $criterios){
        
        if($type == "technicalna"){
            $sub = self::sortArrayForNAEvaluations($sub, $opp);
        }
        $inscritos = [];

        foreach ($sub as $reg) {
            $noteSection = [];
            foreach ($sections as $sec) {
                if(empty($sec->categories) || in_array($reg->category, $sec->categories)){
                    $noteSection[] = self::getSectionNote($opp, $reg, $sec->id);
                }
            }
    
            $now  = new DateTime("now");
            $birth = new DateTime($reg->owner->dataDeNascimento);
            $idade = $now->diff($birth);
    
            $inscritos[] = [
                'number' => $reg->number,
                'name' => $reg->owner->name,
                'preliminaryResult' => $reg->preliminaryResult,
                'consolidatedResult' => $reg->consolidatedResult,
                'category' => $reg->category,
                'birth' => $reg->owner->dataDeNascimento,
                'age' => ($idade->y >= 60 ) ? true : false,
                'noteSection1' => (float) $noteSection[0],
                'noteAllSections' => $noteSection,
            ];
        }
        if($type == "technical") {
            usort($inscritos, function ($a, $b) {
                return [$b['consolidatedResult'], $b['age'], $b['noteSection1'], $a['birth']] <=> [$a['consolidatedResult'], $a['age'], $a['noteSection1'], $b['birth']];
            });
        }
        return $inscritos;
    }
    
    static public function clearCPF_CNPJ($valor){
        $valor = trim($valor);
        $valor = str_replace(".", "", $valor);
        $valor = str_replace(",", "", $valor);
        $valor = str_replace("-", "", $valor);
        $valor = str_replace("/", "", $valor);
        return $valor;
       }

    /**
     * Metodo que converte uma string de json em array
     *
     * @param [type] $value o valor que vem do campo $valueMetas
     * @param [type] $field string do nome do campo do indice array
     * @param [type] $nameField string do nome do campo do valor do array
     * @return void showDecode($valueMeta->value, 'title')
     */
    static public function showDecode($valueStr, $field = null, $nameField) {
        $stringDecodeJson = json_decode($valueStr, true);
        $arrayItens = [];
        foreach($stringDecodeJson as $item) {
            if(!is_null($field)) {
                if(isset($item[$field])){
                    $arrayItens[] = "<strong>Titulo: </strong>".$item[$field]." - ".$item[$nameField];
                }else{
                    $arrayItens[] = $item[$nameField];
                }
            }else{
                $arrayItens[] = $item[$nameField];
            }
            
        }
        echo implode(", ", $arrayItens);
    }

    static public function showAddress($metaData) {
        $endereco = json_decode($metaData, true);

            $additional     = ( isset($endereco['En_Complemento'] ) && $endereco['En_Complemento'] != '' ) ? ", " . $endereco['En_Complemento']: "" ;
            $neighborhood   = ( isset($endereco['En_Bairro'] ) && $endereco['En_Bairro'] != '' ) ? ", " . $endereco['En_Bairro']: "" ;
            $city           = ( isset($endereco['En_Municipio'] ) && $endereco['En_Municipio'] != '' ) ? ", " . $endereco['En_Municipio']: "" ;
            $state          = ( isset($endereco['En_Estado'] ) && $endereco['En_Estado'] != '' ) ? ", " . $endereco['En_Estado']: "" ;
            $cep            = ( isset($endereco['En_CEP'] ) && $endereco['En_CEP'] != '' ) ? ", " . $endereco['En_CEP']: "" ;
            $address_number = ( isset($endereco['En_Num'] ) && $endereco['En_Num'] != '' ) ? ", " . $endereco['En_Num']: "" ;
            $street         = ( isset($endereco['En_Nome_Logradouro'] ) && $endereco['En_Nome_Logradouro'] != '' ) ? $endereco['En_Nome_Logradouro']: "" ;
            //montando endereço caso o $endereco == null
            $address = $street .  $address_number . $additional . $neighborhood . $cep . $city . $state;
            echo $address;
    }
    static public function showItensCheckboxes($str) {
        $strToarray = explode(',', $str);
        $items = "";
        foreach($strToarray as $options){
            $item = trim(preg_replace('/\PL/u', ' ', $options)).",";
            $items .= ' '.$item;            
        }
        echo substr($items, 0 ,-1);
    }
    
    static public function showAgenteOwnerField($field, $metaData, $owner) {
        $valueField = null;
        if ($field == '@location') {
            if(!is_null($owner['En_Complemento'])) {
                $valueField = "CEP: ".$owner['En_CEP'].', 
                Logradouro: '.$owner['En_Nome_Logradouro'].', 
                Nº: '.$owner['En_Num'].', Comp: '.$owner['En_Complemento'].', 
                Bairro: '.$owner['En_Bairro'].', 
                Cidade: '.$owner['En_Municipio'].', 
                UF: '.$owner['En_Estado'];
                
            }else{
                $valueField = "CEP: ".$owner['En_CEP'].', 
                Logradouro: '.$owner['En_Nome_Logradouro'].', 
                Nº: '.$owner['En_Num'].', 
                Bairro: '.$owner['En_Bairro'].', 
                Cidade: '.$owner['En_Municipio'].', 
                UF: '.$owner['En_Estado'];              
            }
            
        }elseif( $field == '@terms:area' ||
            $field == 'longDescription'){
            $valueField = trim(preg_replace('/\PL/u', ' ', $metaData));
        }elseif($field == 'name' || $field == 'nomeCompleto' || $field == 'shortDescription' ||
                $field == "genero" || $field == 'telefone1' || $field == 'telefone2' || $field == 'emailPrivado' || $field == 'emailPublico') {
                
                $valueField = $owner[$field];

        }elseif($field == "facebook" || $field == "intagram" || 
                $field == "twitter" || $field == "site" || 
                $field == "googleplus"){
                $valueField = str_replace(array('\\', '"'), '', $metaData);
        }
        elseif( $field == 'dataDeNascimento') {
            
            $date = DateTime::createFromFormat('Y-m-d', $owner['dataDeNascimento']);
            $valueField = $date->format('d/m/Y');

        }elseif($field == 'documento') { // PARA FORMATAR CPF OU CNPJ
            $doc =  self::clearCPF_CNPJ($owner[$field]); // retirando formatação caso venha
            $str = strlen($doc); // total de carecteres
            if($str == 11) {
                $valueField = self::mask($doc,'###.###.###-##');
            }else{
                $valueField = self::mask($doc,'##.###.###/####-##');
            }
        }
        if(!is_null($valueField)) {
            echo $valueField;
        }else{
            echo '<span class="my-reg-font-10">Não informado</span>';
        }
    }


    static public function showAgentCollectiveField($field,$metaData ) {
        if ($field == '@location') {
            self::showAddress($metaData);
        }else 
        if( $field == 'name' || $field == '@terms:area' || 
            $field == 'shortDescription' || $field == 'longDescription' ||
            $field == 'telefone1' || $field == 'telefone2') {
            echo trim(preg_replace('/\PL/u', ' ', $metaData))."";
        }else 
        if($field == '@links') {
            self::showDecode($metaData, 'title', 'value');
        }
        else
        if($field == "facebook" || $field == "intagram" || $field == "twitter" || $field == "site"){
            echo str_replace(array('\\', '"'), '', $metaData); 
        }
    }

    static public function showSpaceField($field, $metaData) {
        if($field == '@location') {
            self::showAddress($metaData);
        }else
        if( $field == 'name' || $field == '@terms:area' || 
            $field == 'shortDescription' || 
            $field == 'longDescription') {
            echo trim(preg_replace('/\PL/u', ' ', $metaData));
        }else
        if($field == '@links') {
            self::showDecode($metaData, 'title', 'value');
        }else
        if( $field == 'telefone1' || $field == 'telefone2' )
        {
            echo str_replace(array('\'', '"'), '', $metaData); 
        }
        else
        if($field == "facebook" || $field == "intagram" || $field == "twitter" || $field == "site"){
            echo str_replace(array('\\', '"'), '', $metaData); 
        }else{
            echo trim(preg_replace('/\PL/u', ' ', $metaData));
        }
    }

    static public function getDependenciesField($registration, $fields) {
      
        $app = App::i();
        $show = true;
        $fieldRegMeta = '';
        $valueRegMeta = '';

        if($fields['fieldType'] !== 'file'){
            if(is_array($fields['config'])) {    
                foreach ($fields['config'] as $keyConf => $valConf) {
                    if(isset($valConf['value'])) {
                       $valueRegMeta = $valConf['value'];                   
                       $fieldRegMeta = $valConf['field'];                   
                    }
                }
            }
            
            $regField = $app->repo('RegistrationMeta')->findBy([
                'owner' =>$registration,
                'key' => $fieldRegMeta
            ]);
           
            foreach ($regField as $key => $valregField) {
                if($valueRegMeta !== $valregField->value) {
                    $show = false;
                }
            }
        }
        
       return $show;
    }

    /**
     * Metodo para verificação dos arquivos enviados na oportunidade
     *
     * @param [type] $registration
     * @param [type] $fileGroup
     * @return void
     */
    static public function getFileRegistration($registration, $fileGroup) {
        $app = App::i();

        $file = $app->repo('RegistrationFile')->findBy([
            'owner' => $registration,
            'group' => $fileGroup
        ]);

        if(count($file) > 0) {
            return $file;
        }
    }
    
    static public function showAllFieldAndFile($registration) {
        $fields = []; // array vazio

        $registrationOpportunity = $registration->opportunity;
        foreach ($registrationOpportunity->registrationFieldConfigurations as $field) {
            //ATRIBUINDO ARRAY DOS CAMPOS AO ARRAY
            array_push($fields , [
                'displayOrder' => $field->displayOrder,
                'id' => $field->id,
                'title' => $field->title,
                'description' => $field->description,
                'fieldType' => $field->fieldType,
                'config' => $field->config,
                'owner' => $field->owner,
                'categories' => $field->categories                        
            ]);
        }
        //VERIFICANDO DE TEM ARQUVIOS
        if($registrationOpportunity->registrationFileConfigurations->count() > 0) {
            
            foreach ($registrationOpportunity->registrationFileConfigurations as $key => $file) {
                $fileRegistration = self::getFileRegistration($registration, $file->fileGroupName);
                
                $registrationFile = (array) $fileRegistration;
                $config = [];
                //
                if($file->multiple) {
                    foreach ($registrationFile as $key => $fileValue) {                       
                        array_push($config, [
                            'id'    => $fileValue->id,
                            'group' => $fileValue->group,
                            'name'  => $fileValue->name,
                            'owner' => $fileValue->owner
                        ]);
                    }
                }else {
                    foreach ($registrationFile as $key => $conf) {
                        array_push($config, [
                            'id'    => $conf->id,
                            'group' => $conf->group,
                            'name'  => $conf->name,
                            'owner' => $conf->owner
                        ]);
                    }
                }
                array_push($fields , [
                    'displayOrder' => $file->displayOrder,
                    'id' => $file->id,
                    'title' => $file->title,
                    'description' => $file->description,
                    'fieldType' => 'file',
                    'config' => $config,
                    'owner' => $file->owner,
                    'multiple' => $file->multiple,
                    'categories' => $file->categories     
                ]);
            }
        }
        $column_order = array_column( $fields, 'displayOrder' );
        array_multisort( $column_order, SORT_ASC, $fields );
        
        return $fields;
    }

    static public function sortArrayForNAEvaluations($sub, $opp){

        $app = App::i();

        $committee = $opp->getEvaluationCommittee();
        
        $users = [];
        foreach ($committee as $item) {
            $users[] = $item->agent->user->id;
        }

        $status = [ 
            \MapasCulturais\Entities\RegistrationEvaluation::STATUS_EVALUATED,
            \MapasCulturais\Entities\RegistrationEvaluation::STATUS_SENT
        ];

        usort($sub, function($item1,$item2) use ($app, $users, $status){
            // Comparação das notas para saber se os itens possuem a mesma nota consolidade para que possamos fazer a verificação das notas dos criterios
            if ($item1->consolidatedResult == $item2->consolidatedResult) {
                // Pegando as avaliações dos objetos que estão sendo comparados para ordenação;
                $evaluations_1 = $app->repo('RegistrationEvaluation')->findByRegistrationAndUsersAndStatus($item1, $users, $status);
                $evaluations_2 = $app->repo('RegistrationEvaluation')->findByRegistrationAndUsersAndStatus($item2, $users, $status);
                $eval_1;
                $eval_2;
                foreach ($evaluations_1 as $eval){
                    if(empty($eval_1)){
                        $eval_1 = $eval->evaluationData;
                    }else{
                        $notes = $eval->evaluationData;
                        foreach($notes as $key => $value){
                            if($key != 'na' && $key != 'obs'){
                                if($eval_1->$key == "" && $value == ""){
                                }else if($eval_1->$key == ""){
                                    $eval_1->$key = $value;
                                }else if($value == ""){
                                }else{
                                    $eval_1->$key += $value;
                                    $eval_1->$key = $eval_1->$key/count($users);
                                }
                            }
                        }
                    }
                }
                foreach ($evaluations_2 as $eval){
                    if(empty($eval_2)){
                        $eval_2 = $eval->evaluationData;
                    }else{
                        $notes = $eval->evaluationData;
                        foreach($notes as $key => $value){
                            if($key != 'na' && $key != 'obs'){
                                if($eval_2->$key == "" && $value == ""){
                                }else if($eval_2->$key == ""){
                                    $eval_2->$key = $value;
                                }else if($value == ""){
                                }else{
                                    $eval_2->$key += $value;
                                    $eval_2->$key = $eval_2->$key/count($users);
                                }
                            }
                        }
                    }
                }
                foreach($eval_1 as $key => $value){
                    if($key != 'na' && $key != 'obs'){
                        if($value != "" && $eval_2->$key == ""){
                            return -1;
                        }else if($value == "" && $eval_2->$key != ""){
                            return 1;
                        }else if($value < $eval_2->$key){
                            return 1;
                        }else if($value > $eval_2->$key){
                            return -1;
                        }
                    }
                }
                return 0;
            }
            return ($item1->consolidatedResult < $item2->consolidatedResult) ? 1 : -1;
        });
        return $sub;
    }
}