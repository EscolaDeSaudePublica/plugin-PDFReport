<?php
namespace PDFReport\Controllers;

require PLUGINS_PATH.'PDFReport/vendor/autoload.php';
require PLUGINS_PATH.'PDFReport/vendor/dompdf/dompdf/src/FontMetrics.php';
use DateTime;
use \MapasCulturais\App;
use Dompdf\Dompdf;
use Dompdf\Options;
use PDFReport\Entities\Pdf as EntitiesPdf;
use Mpdf\Mpdf;

class Pdf extends \MapasCulturais\Controller{

    function GET_gerarPdf() {
        
        $domPdf = new Dompdf();
        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setIsHtml5ParserEnabled(true);
       
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
        $app = App::i();
        
        $regs       = "";
        $title      = "";
        $opp        = "";
        $template   = "";
        //NULO PARA CASOS DE NÃO TER RECURSO
        $claimDisabled = null;

        switch ($this->getData['selectRel']) {
            case 0:
                // $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 'ALL');
                // $title      = 'Relatório de inscritos na oportunidade';
                // $template   = 'pdf/teste';
                $_SESSION['error'] = "Ops! Selecione uma opção";
                $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                break;
            case 1:
                $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 'ALL');
                $title      = 'Relatório de inscritos na oportunidade';
                $template   = 'pdf/subscribers';
                
                //SE VAZIO, É POR QUE NÃO TEM INSCRITO
                if(empty($regs['regs'])){
                    $_SESSION['error'] = "Ops! Não tem inscrito nessa oportunidade.";
                    $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                }
                break;
            case 2:
                //BUSCANDO TODOS OS REGISTROS
                $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 10);
                if(empty($regs['regs'])){
                    $_SESSION['error'] = "Ops! A oportunidade deve estar publicada.";
                    $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                }
                $verifyResource = $this->verifyResource($this->getData['idopportunityReport']);
                    
                //SE TIVER RECURSO, RECEBE O VALOR QUE ESTÁ NA TABELA
                if(isset($verifyResource[0])){
                    $claimDisabled = $verifyResource[0]->value;
                }
               
                $title      = 'Resultado Preliminar do Certame';
                $template   = 'pdf/preliminary';
                break;
            case 3:
                //ESSE CASE, VERIFICA SE OS RECURSOS E A OPORTUNIDADE
                $id = $this->getData['idopportunityReport'];
                //RETORNANDO O PERIDO DE RECURSO
                $dqlOpMeta = "SELECT op FROM 
                MapasCulturais\Entities\OpportunityMeta op
                WHERE op.owner = {$id}";
                $queryMeta = $app->em->createQuery($dqlOpMeta);
                $resultOpMeta = $queryMeta->getResult();
                $period = false;
                $dateInit = "";
                $dateEnd = "";
                $hourInit = "";
                $hourEnd = "";
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
                // dump($dateHourNow->format('Y-m-d H:i:s'));
                $dateAndHourInit = $dateInit.' '.$hourInit;
                $dateVerifyPeriod = DateTime::createFromFormat('d/m/Y H:i:s', $dateAndHourInit);
                if($dateHourNow > $dateVerifyPeriod){
                    $period = true;
                }
                if($period) {
                    $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 10);
                    if(empty($regs['regs'])){
                        $_SESSION['error'] = "Ops! Para gerar o relatório definitivo a oportunidade deve estar publicada.";
                        $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                    }
                    
                    //SELECT AOS RECURSOS
                    $dql = "SELECT r
                    FROM 
                    Saude\Entities\Resources r
                    WHERE r.opportunityId = {$id}";
                    $query = $app->em->createQuery($dql);
                    $resource = $query->getResult();
                    $countPublish = 0;//INICIANDO VARIAVEL COM 0
                    foreach ($resource as $key => $value) {
                        if($value->replyPublish == 1 && $value->opportunityId->publishedRegistrations == 1) {
                            $countPublish++;//SE ENTRAR INCREMENTA A VARIAVEL
                        }else{
                            $countPublish = 0;
                        }
                    }
                    //SE OS DOIS VALORES BATEREM, ENTÃO GERA O PDF
                    //O PDF SOMENTE SERÁ GERADO NA EVENTUALIDADE DA AOPORTUNIDADE ESTÁ PUBLICADA E OS RECURSOS TBM ESTIVEREM PUBLICADOS
                    
                    if($countPublish == count($resource) && $countPublish > 0 && count($resource) > 0) {
                        $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 10);
                        $title      = 'Resultado Definitivo do Certame';
                        $template   = 'pdf/definitive';
                       
                    }elseif($countPublish == count($resource) && $countPublish == 0 && count($resource) == 0){
                       
                        //SE NÃO, VOLTA PARA A PÁGINA DA OPORTUNIDADE COM AVISO
                        //$app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                        $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 10);
                        
                        if(empty($regs['regs'])) {
                            $_SESSION['error'] = "Ops! Você deve publicar a oportunidade para esse relatório";
                            $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport'].'#/tab=inscritos'), 401);
                        }
                        //VERIFICANDO SE TEM RECURSO
                        $verifyResource = $this->verifyResource($this->getData['idopportunityReport']);
                        
                        //SE TIVER RECURSO, RECEBE O VALOR QUE ESTÁ NA TABELA
                        if(isset($verifyResource[0])){
                            $claimDisabled = $verifyResource[0]->value;
                        }
                        
                        //EM CASOS DE TER INSCRIÇÃO MAS NÃO TEM RECURSO OU ESTÁ DESABILITADO
                        if(isset($regs['regs'][0]) && empty($verifyResource) || $claimDisabled == 1 ){
                            $title      = 'Resultado Definitivo do Certame';
                            $template   = 'pdf/definitive';
                        }
                        elseif(isset($regs['regs'][0]) && empty($verifyResource) || $claimDisabled == 0)
                        //CASO ESTEJA PUBLICADO E NÃO TEM RECURSO
                        {
                            $title      = 'Resultado Definitivo do Certame';
                            $template   = 'pdf/definitive';
                        }else{
                            $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport'].'#/tab=inscritos'), 401);
                        }
                    
                    }
                }else{
                    $_SESSION['error'] = "Ops! Ocorreu um erro inesperado.";
                    $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport'].'#/tab=inscritos'), 401);
                }
                break;
            case 4:
                $regs = $this->oportunityRegistrationAproved($this->getData['idopportunityReport'], 10);
                if(empty($regs['regs'])){
                    $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                }
                $title      = 'Relatório de contato';
                $template   = 'pdf/contact';
                break;
            default:
                $app->redirect($app->createUrl('oportunidade/'.$this->getData['idopportunityReport']), 401);
                break;
        }
        // dump(getType($regs));
        $app->view->jsObject['opp'] = $regs['opp'];
        $app->view->jsObject['subscribers'] = $regs['regs'];
        $app->view->jsObject['title'] = $title;
        $app->view->jsObject['claimDisabled'] = $claimDisabled;
 
        $app->render($template); 
        // $content = $app->view->fetch($template);
        
        // $domPdf->loadHtml($content);
        // $domPdf->setPaper('A4', 'portrait');
        // $domPdf->render();
        // // Output the generated PDF to Browser
        // //$domPdf->stream();
        // $domPdf->stream("relatorio.pdf", array("Attachment" => false));
        // exit(0);
    }

    /**
     * Busca a oportunidade e todos os aprovados da inscrição 
     *
     * @param [integer] $idopportunity
     * @return void array
     */
    function oportunityRegistrationAproved($idopportunity, $status) 
    {
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

    function verifyResource($idOportunidade) {
        $app = App::i();
        $opp = $app->repo('OpportunityMeta')->findBy(['owner'=>$idOportunidade,'key'=>'claimDisabled']);
        return $opp;
    }

    function GET_minha_inscricao() {
        ini_set('display_errors', 1);
        $app = App::i();
        //SOMENTE AUTENTICADO
        if($app->user->is('guest')){
            $app->auth->requireAuthentication();
        }

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__) . '/vendor/mpdf/mpdf/tmp','mode' => 'utf-8','format' => 'A4']);
        
        $reg = $app->repo('Registration')->find($this->data['id']);

        //SE O DONO DA INSCRIÇÃO NAO FOR O MESMO LOGADO, ENTÃO NÃO TEM PERMISSÃO DE ACESSAR.
        if($reg->owner->userId != $app->user->id) {
           //SE OS IDS FOREM DIFERENTE, VERIRICA SE ELE NAO É UM ADMIN PARA RETORNAR A PÁGINA ANTERIOR           
            if(!$reg->opportunity->owner->canUser('@control')){                
                $_SESSION['error'] = "Ops! Você não tem permissão";               
                $app->redirect($app->request()->getReferer(), 403);            
            }
        }
       
        //INSTANCIA DO TIPO ARRAY OBJETO
        $app->view->regObject = new \ArrayObject;
        $app->view->regObject['ins'] = $reg;
        //CRIANDO UM ARRAY COM SOMENTE ALGUNS ITENS DO OBJETO
        $fields = EntitiesPdf::showAllFieldAndFile($reg->opportunity);
       
        
        //ORDENANDO O ARRAY EM ORDEM DE ID
       
        dump($fields);
        die;
        $registrationFieldConfigurations = $fields;
        $app->view->regObject['fieldsOpportunity'] = $registrationFieldConfigurations;

        $template   = 'pdf/my-registration';
        //$app->render($template);
        ob_start();
        $content = $app->view->fetch($template);

        $footer = '<div style="border-top: 1px solid #c5c5c5;">
        <p style="text-align: center; font-size: 10px;"><span>Escola de Saúde Pública do Ceará Paulo Marcelo Martins Rodrigues</span></p>
        <p style="text-align: center; font-size: 10px;"><span>Av. Antônio Justa, 3161 - Meireles - CEP: 60.165-090</span></p>
        <p style="text-align: center; font-size: 10px;"><span>Fortaleza / CE - Fone: (85) 3101.1398</span></p>
        </div>';
                
        $mpdf->SetHTMLFooter($footer);
        $mpdf->SetHTMLFooter($footer, 'E');
        $mpdf->writingHTMLfooter = true;
        //$mpdf->SetDisplayMode('fullpage');
        $mpdf->SetTitle('Mapa da Saúde - Relatório');
        $stylesheet = file_get_contents(PLUGINS_PATH.'PDFReport/assets/css/stylePdfReport.css');
        $mpdf->WriteHTML(ob_get_clean());
        $mpdf->WriteHTML($stylesheet,1);
        $mpdf->WriteHTML($content,2);
        $mpdf->Output();
        exit;
    }

    
}