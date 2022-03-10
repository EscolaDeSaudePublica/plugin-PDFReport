<?php
    use PDFReport\Entities\Pdf;

    $this->layout = 'nolayout-pdf';
    $sub = $app->view->jsObject['subscribers'];
    $nameOpportunity = $sub[0]->opportunity->name;
    $opp = $app->view->jsObject['opp'];
    $sections = $opp->evaluationMethodConfiguration->sections;
    $criterios = $opp->evaluationMethodConfiguration->criteria;
    /** Registro para puxar a ordem do relatorio de acordo com as informações. */
    $inscritos = Pdf::getResultsByOrder($type, $sub, $opp, $sections, $criterios);
    $classificacao = [];
    $categorias = $opp->registrationCategories;
    foreach($categorias as $cat){
        $classificacao[$cat] = 0;
    }
?>
<div class="container">
<?php foreach ($opp->registrationCategories as $key_first => $nameCat) :?>
    <div class="table-info-cat">
        <span><?php echo $nameCat; ?></span>
    </div>
    <table id="table-preliminar" width="100%">
        <thead>
            <tr style="border: 1px solid #CFDCE5;">
                <?php if(isset($preliminary)): echo '<th class="text-left" width="10%">Classificação</th>'; ?> <?php endif;?>
                <th class="text-left" style="margin-top: 5px;" width="22%">Inscrição</th>
                <th class="text-left" width="68%">Candidatos</th>
                <?php if(isset($preliminary)): echo '<th class="text-center" width="10%">NF</th>'; ?> 
                <?php else: ?>
                    <?php foreach ($sections as $key => $sec) :?>
                        <?php if(empty($sec->categories) || in_array($nameCat, $sec->categories)): ?>
                            <th class="text-center" width="<?php echo count($sections) > 1 ? "5%" : "10%" ?>"><?php echo 'N'.($key + 1).'E' ?></th>
                        <?php endif;?>
                    <?php endforeach; ?>
                <?php endif;?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($inscritos as $key => $ins) :?>
                <?php if($nameCat == $ins['category']) :?> 
                    <tr>
                        <?php if(isset($preliminary)) :?> 
                            <td class="text-center"><?php echo $classificacao[$nameCat] + 1 ?> </td>
                        <?php endif;?>
                        <?php $classificacao[$nameCat] = $classificacao[$nameCat] + 1; ?>
                        <td class="text-left"><?php echo $ins['number']; ?></td>
                        <td class="text-left"><?php echo mb_strtoupper($ins['name']); ?></td>
                        <?php if($type == "technicalna" && !isset($preliminary)):?> 
                            <td class="text-center"><?php echo $ins['preliminaryResult']; ?></td>
                        <?php elseif(isset($preliminary)): ?> 
                            <td class="text-center"><?php echo $ins['consolidatedResult']; ?></td>
                        <?php else: ?> 
                            <?php foreach($ins['noteAllSections'] as $noteSection) :?>
                                <td class="text-center"><?php echo $noteSection; ?></td>
                            <?php endforeach; ?>
                        <?php endif;?>
                    </tr>
                <?php endif;?>
            <?php endforeach; ?>
            <?php if($classificacao[$nameCat] == 0) :?> 
                <tr class="no-subs">
                    <td width="10%"></td>
                    <td class="text-left">Não há candidatos selecionados</td>
                </tr>
            <?php endif;?>
        </tbody>
    </table>
<?php endforeach; ?>
</div>