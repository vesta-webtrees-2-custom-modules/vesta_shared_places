<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Vesta\CommonI18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelFactRestriction;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelRadioButton;
use Vesta\ControlPanelUtils\Model\ControlPanelRadioButtons;
use Vesta\ControlPanelUtils\Model\ControlPanelRange;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;

trait SharedPlacesModuleTrait {

  protected function getMainTitle() {
    return CommonI18N::titleVestaSharedPlaces();
  }

  public function getShortDescription() {
    return
            I18N::translate('A module providing support for shared places.') . ' ' .
            I18N::translate('Replacement for the original \'Locations\' module.');
  }

  protected function getFullDescription() {
    $link1 = '<a href="https://github.com/vesta-webtrees-2-custom-modules/vesta_shared_places">'.CommonI18N::readme().'</a>';
    $link2 = '<a href="https://github.com/vesta-webtrees-2-custom-modules/vesta_common/blob/master/docs/LocationData.md">'.CommonI18N::readmeLocationData().'</a>';

    $description = array();    
    //TODO add link to https://genealogy.net/GEDCOM/
    $description[] = /* I18N: Module Configuration */I18N::translate('A module supporting shared places as level 0 GEDCOM objects, on the basis of the GEDCOM-L Addendum to the GEDCOM 5.5.1 specification. Shared places may contain e.g. map coordinates, notes and media objects. The module displays this data for all matching places via the extended \'Facts and events\' tab. It may also be used to manage GOV ids, in combination with the Gov4Webtrees module.');
    $description[] = /* I18N: Module Configuration */I18N::translate('Replaces the original \'Locations\' module.');
    $description[] = 
            CommonI18N::requires2(CommonI18N::titleVestaCommon(), CommonI18N::titleVestaPersonalFacts());
    $description[] = 
            CommonI18N::providesLocationData();
    $description[] = $link1 . '. ' . $link2 . '.';;
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            CommonI18N::displayedTitle(),
            array(
                /*new ControlPanelCheckbox(
                I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),*/
        new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInListTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_LIST',
                '1')));
    
    $link = '<a href="https://github.com/vesta-webtrees-2-custom-modules/vesta_shared_places">'.CommonI18N::readme().'</a>';
    
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Shared place structure'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Use hierarchical shared places'),
                /* I18N: Module Configuration */I18N::translate('If checked, relations between shared places are modelled via an explicit hierarchy, where shared places have XREFs to higher-level shared places, as described in the specification.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('Note that this also affects the way shared places are created, and the way they are mapped to places.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('In particular, hierarchical shared places do not have names with comma-separated name parts.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('See %1$s for details.', $link) . ' ' .
                /* I18N: Module Configuration */I18N::translate('There is a data fix available which may be used to convert existing shared places.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('When unchecked, the former approach is used, in which hierarchies are only hinted at by using shared place names with comma-separated name parts.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('It is strongly recommended to switch to hierarchical shared places.'),
                'USE_HIERARCHY',
                '1')));
    
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Linking of shared places to places'),
            array(
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Additionally link shared places via name'),
                /* I18N: Module Configuration */I18N::translate('According to the GEDCOM-L Addendum, shared places are referenced via XREFs, just like shared notes etc. ') .
                /* I18N: Module Configuration */I18N::translate('It is now recommended to use XREFs, as this improves performance and flexibility. There is a data fix available which may be used to add XREFs. ') .
                /* I18N: Module Configuration */I18N::translate('However, you can still check this option and link shared places via the place name itself. In this case, links are established internally by searching for a shared place with any name matching case-insensitively.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('If you are using hierarchical shared places, a place with the name "A, B, C" is mapped to a shared place "A" with a higher-level shared place that maps to "B, C".'),
                'INDIRECT_LINKS',
                '0'),
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('... and fall back to n parent levels'),
                /* I18N: Module Configuration */I18N::translate('When the preceding option is checked, and no matching shared place is found, fall back to n parent places (so that e.g. for n=2 a place "A, B, C" would also match shared places that match "B, C" and "C")'),
                0,
                5,
                'INDIRECT_LINKS_PARENT_LEVELS',
                0)));
    
    $factsSub = array();
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('All shared place facts'),
            array(     
        ControlPanelFactRestriction::createWithFacts(
                SharedPlacesModuleTrait::getPicklistFactsLoc(),
                /* I18N: Module Configuration */I18N::translate('This is the list of GEDCOM facts that your users can add to shared places. You can modify this list by removing or adding fact names as necessary. Fact names that appear in this list must not also appear in the “Unique shared place facts” list.'),
                '_LOC_FACTS_ADD',
                'NAME,_LOC:TYPE,NOTE,SHARED_NOTE,SOUR,_LOC:_LOC')));
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Unique shared place facts'),
            array(     
        ControlPanelFactRestriction::createWithFacts(
                SharedPlacesModuleTrait::getPicklistFactsLoc(),
                /* I18N: Module Configuration */I18N::translate('This is the list of GEDCOM facts that your users can only add once to shared places. For example, if NAME is in this list, users will not be able to add more than one NAME record to a shared place. Fact names that appear in this list must not also appear in the “All shared place facts” list.'),
                '_LOC_FACTS_UNIQUE',
                'MAP,_GOV')));
    
    //really not that useful currently
    /*
    $factsSub[] = new ControlPanelSubsection(
            I18N::translate('Facts for new shared places'),
            array(     
        ControlPanelFactRestriction::createWithFacts(
                SharedPlacesModuleTrait::getPicklistFactsLoc(true),
                I18N::translate('This is the list of GEDCOM facts that will be shown when adding a new shared place.'),
                '_LOC_FACTS_REQUIRED',
                '')));
    */
    
    $factsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Quick shared place facts'),
            array(
        ControlPanelFactRestriction::createWithFacts(
                SharedPlacesModuleTrait::getPicklistFactsLoc(),
                /* I18N: Module Configuration */I18N::translate('This is the list of GEDCOM facts that your users can add to shared places. You can modify this list by removing or adding fact names as necessary. Fact names that appear in this list must not also appear in the “Unique shared place facts” list. '),
                '_LOC_FACTS_QUICK',
                'NAME,_LOC:_LOC,MAP,NOTE,SHARED_NOTE,_GOV')));
    
    $factsAndEventsSub = array();
    $factsAndEventsSub[] = new ControlPanelSubsection(
            CommonI18N::displayedData(),
            array(     
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Restrict to specific facts and events'),
                /* I18N: Module Configuration */I18N::translate('If this option is checked, shared place data is only displayed for the following facts and events. ') .
                CommonI18N::bothEmpty(),
                'RESTRICTED',
                '0'),
        ControlPanelFactRestriction::createWithIndividualFacts(
                CommonI18N::restrictIndi(),
                'RESTRICTED_INDI',
                'BIRT,OCCU,RESI,DEAT'),
        ControlPanelFactRestriction::createWithFamilyFacts(
                CommonI18N::restrictFam(),
                'RESTRICTED_FAM',
                'MARR')));

    $factsAndEventsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Automatically expand shared place data'),
            array(new ControlPanelRadioButtons(
                false,
                array(
            new ControlPanelRadioButton(
                    ' './* I18N: Module Configuration */I18N::translate('no'),
                    null,
                    '0'),
            new ControlPanelRadioButton(
                    /* I18N: Module Configuration */I18N::translate('yes, but only the first occurrence of the shared place'),
                    /* I18N: Module Configuration */I18N::translate('Note that the first occurrence may be within a toggleable, currently hidden fact or event (such as an event of a close relative). This will probably be improved in future versions of the module.'),
                    '1'),
            new ControlPanelRadioButton(
                    /* I18N: Module Configuration */I18N::translate('yes'),
                    null,
                    '2')),
                null,
                'EXPAND',
                '1')));

    $listSub = array();
    $listSub[] = new ControlPanelSubsection(
            CommonI18N::displayedData(),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show link counts for shared places list'),
                /* I18N: Module Configuration */I18N::translate('Determining the link counts (linked individual/families) is expensive when assigning shared places via name, and therefore causes delays when the shared places list is displayed. It\'s recommended to only select this option if places are assigned via XREFs.'),
                'LINK_COUNTS',
                '1')));

    $hierarchySub = array();
    $hierarchySub[] = new ControlPanelSubsection(
            CommonI18N::displayedData(),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Filter to unique shared places'),
                /* I18N: Module Configuration */I18N::translate('In the place hierarchy list, when using the option \'restrict to shared places\', shared places with multiple names show up multiple times as separate entries. Check this option to show each shared place only once in this case, under the shared place\'s primary name, and also show its additional names.'),
                'UNIQUE_SP_IN_HIERARCHY',
                '0')));
    
    $sections = array();
    $sections[] = new ControlPanelSection(
            CommonI18N::general(),
            null,
            $generalSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Facts for shared place records'),
            null,
            $factsSub);
    $sections[] = new ControlPanelSection(
            CommonI18N::factsAndEventsTabSettings(),
            null,
            $factsAndEventsSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Shared places list'),
            null,
            $listSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Place hierarchy'),
            null,
            $hierarchySub);

    return new ControlPanelPreferences($sections);
  }

  public static function getPicklistFactsLoc(bool $forRequired = false): array {
    $tags = [
        "NAME",
        "_LOC:TYPE",
        "NOTE",
        "SHARED_NOTE",
        "SOUR",
        "_LOC:_LOC",
        "MAP",
        "_GOV"];
    
    if ($forRequired) {
      //others are redundant, tricky, or anyway TBI
      $tags = [
        "NOTE"];
      
        //"SHARED_NOTE" problematic (potential modal within modal)
    }
    
    $facts = [];
    foreach ($tags as $tag) {
        $facts[$tag] = GedcomTag::getLabel($tag);
    }
    uasort($facts, '\Fisharebest\Webtrees\I18N::strcasecmp');

    return $facts;
  }
}
