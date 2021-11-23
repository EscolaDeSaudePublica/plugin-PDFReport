<?php 
    use Saude\Utils\RegistrationStatus;
    
    $this->layout = 'nolayout-pdf'; 
    $sub = $app->view->jsObject['subscribers'];
    $nameOpportunity = $sub[0]->opportunity->name;
    $opp = $app->view->jsObject['opp'];
?>

<div class="container">
    <table id="table-preliminar" width="100%" >
        <thead>
            <tr>
                <th class="text-left" width="30%">Inscrição</th>
                <th class="text-left" width="50%">Agentes candidatos</th>
                <th class="text-left" width="20%">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $isExist = false;
            foreach($sub as $key => $nameSub){?>
                    <tr>
                        <td><?php echo $nameSub->number; ?></td>
                        <td><?php echo $nameSub->owner->name; ?></td>
                        <td><?php echo RegistrationStatus::getStatusNameById($nameSub->status); ?> </td>
                    </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>
    