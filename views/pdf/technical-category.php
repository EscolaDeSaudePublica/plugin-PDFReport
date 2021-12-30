<?php 
    use MapasCulturais\App;
    use Saude\Utils\RegistrationStatus;
    use PDFReport\Entities\Pdf;

    $this->layout = 'nolayout-pdf'; 
    $sub = $app->view->jsObject['subscribers'];
    $nameOpportunity = $sub[0]->opportunity->name;
    $opp = $app->view->jsObject['opp'];
    $sections = $opp->evaluationMethodConfiguration->sections;
    $criterios = $opp->evaluationMethodConfiguration->criteria;

    function invenDescSort($item1,$item2){
        if ($item1->consolidatedResult == $item2->consolidatedResult) return 0;
        return ($item1->consolidatedResult < $item2->consolidatedResult) ? 1 : -1;
    }
    if($type == "technicalna" && isset($preliminary)){
        $sub = Pdf::sortArrayForNAEvaluations($sub, $opp);
    }else if(!isset($preliminary)){}
    else{
        usort($sub, 'invenDescSort');
    }
?>
<div class="container">
    <?php 
    foreach ($opp->registrationCategories as $key_first => $nameCat) :?>
        <div class="table-info-cat">
            <span><?php echo $nameCat; ?></span>
        </div>
        <table id="table-preliminar" width="100%">
            <thead>
                <tr style="border: 1px solid #CFDCE5;">
                    <?php 
                        if(isset($preliminary)){
                            echo '<th class="text-left" width="10%">Classificação</th>';
                        }
                    ?>
                    <th class="text-left" style="margin-top: 5px;" width="22%">Inscrição</th>
                    <th class="text-left" width="68%">Candidatos</th>
                    <?php 
                        if($type == "technicalna"){
                            echo '<th class="text-center" width="10%">NP</th>' ;
                        }
                        else if(isset($preliminary)){
                            echo '<th class="text-center" width="10%">NF</th>' ;
                        }else{
                            foreach($sections as $key => $sec){
                                if(in_array($nameCat, $sec->categories)){ ?>
                                    <th class="text-center" width="<?php echo count($sections) > 1 ? "5%" : "10%" ?>"><?php echo 'N'.($key + 1).'E' ?></th>
                        <?php   }
                            }
                        }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $countArray = [];
                $arrayCheck = [];
                foreach($sub as $key => $nameSub){
                    if($nameCat == $nameSub->category){
                        $countArray[$nameCat][] = $key;
                        $arrayCheck[] = $nameSub->category;
                        ?>
                        <tr>
                            <?php 
                                if(isset($preliminary)){ ?>
                                    <td class="text-center"><?php echo count($countArray[$nameCat]) ?> </td>
                                <?php }
                            ?>
                            <td class="text-left"><?php echo $nameSub->number; ?></td>
                            <td class="text-left"><?php echo $nameSub->owner->name; ?></td>
                            <?php 
                                if($type == "technicalna"){ ?>
                                    <td class="text-center"><?php echo $nameSub->preliminaryResult; ?></td>
                                <?php }else if(isset($preliminary)){ ?>
                                    <td class="text-center"><?php echo $nameSub->consolidatedResult; ?></td>
                                <?php } else{
                                    foreach($sections as $key => $sec){ 
                                        if(in_array($nameSub->category, $sec->categories)){ ?>
                                            <td class="text-center"><?php echo Pdf::getSectionNote($opp, $nameSub, $sec->id); ?></td>
                            <?php       } 
                                    }
                                }
                            ?>
                        </tr>
                    <?php
                    }
                }
                
                if(!in_array($nameCat, $arrayCheck)){ ?>
                    <tr class="no-subs">
                        <td width="10%"></td>
                        <td class="text-left">Não há candidatos selecionados</td>
                    </tr>
                <?php }
                ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>
