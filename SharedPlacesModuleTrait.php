<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanel\Model\ControlPanelCheckbox;
use Vesta\ControlPanel\Model\ControlPanelFactRestriction;
use Vesta\ControlPanel\Model\ControlPanelPreferences;
use Vesta\ControlPanel\Model\ControlPanelRadioButton;
use Vesta\ControlPanel\Model\ControlPanelRadioButtons;
use Vesta\ControlPanel\Model\ControlPanelSection;
use Vesta\ControlPanel\Model\ControlPanelSubsection;

trait SharedPlacesModuleTrait {

  protected function getMainTitle() {
    return I18N::translate('Vesta Shared Places');
  }

  public function getShortDescription() {
    return
            I18N::translate('A module providing support for shared places.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = I18N::translate('A module supporting shared places as level 0 GEDCOM objects, on the basis of the Gedcom-L agreements. Shared places may contain coordinates, notes and media objects. Displays this data for all matching places via the extended \'Facts and events\' tab.');
    $description[] = I18N::translate('Requires the \'%1$s Vesta Common\' module, and the \'%1$s Vesta Facts and events\' module.', $this->getVestaSymbol());
    $description[] = I18N::translate('Provides location data to other custom modules.');
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Displayed title'),
            array(new ControlPanelCheckbox(
                /* I18N: Configuration option */I18N::translate('Include the ' . $this->getVestaSymbol() . ' symbol in the module title'),
                null,
                'VESTA',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Configuration option */I18N::translate('Include the ' . $this->getVestaSymbol() . ' symbol in the list title'),
                null,
                'VESTA_LIST',
                '1')));

    $factsAndEventsSub = array();
    $factsAndEventsSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Linking of shared places to places'),
            array(new ControlPanelCheckbox(
                /* I18N: Configuration option */I18N::translate('Additionally link shared places via name'),
                I18N::translate('According to the Gedcom-L agreements, shared places are referenced via xrefs, just like shared notes etc. There is no edit support for this yet, so you have to add a level 3 _LOC @L123@ (with the proper shared place xref) under level 2 PLAC in the raw GEDCOM of a fact or event. ') .
                I18N::translate('This is rather inconvenient, and all places have names anyway, so you can check this option and link shared places via the place name itself. Links are established internally by searching for a shared place with any name matching case-insensitively.'),
                'INDIRECT_LINKS',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Configuration option */I18N::translate('Restrict to specific facts and events'),
                /* I18N: Configuration option */ I18N::translate('If this option is checked, shared place data is only displayed for the following facts and events. ') .
                /* I18N: Configuration option */I18N::translate('In particular if both lists are empty, no additional facts and events of this kind will be shown.'),
                'RESTRICTED',
                '0'),
        new ControlPanelFactRestriction(
                false,
                /* I18N: Configuration option */ I18N::translate('Restrict to this list of GEDCOM individual facts and events. You can modify this list by removing or adding fact and event names, even custom ones, as necessary.'),
                'RESTRICTED_INDI',
                'BIRT,OCCU,RESI,DEAT'),
        new ControlPanelFactRestriction(
                true,
                /* I18N: Configuration option */ I18N::translate('Restrict to this list of GEDCOM family facts and events. You can modify this list by removing or adding fact and event names, even custom ones, as necessary.'),
                'RESTRICTED_FAM',
                'MARR')));

    $factsAndEventsSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Automatically expand shared place data'),
            array(new ControlPanelRadioButtons(
                false,
                array(
            new ControlPanelRadioButton(
                    I18N::translate('	no'),
                    null,
                    '0'),
            new ControlPanelRadioButton(
                    I18N::translate('yes, but only the first occurrence of the shared place'),
                    /* I18N: Configuration option */ I18N::translate('Note that the first occurrence may be within a toggleable, currently hidden fact or event (such as an event of a close relative). This will probably be improved in future versions of the module.'),
                    '1'),
            new ControlPanelRadioButton(I18N::translate('yes'),
                    null,
                    '2')),
                null,
                'EXPAND',
                '1')));

    $listSub = array();
    $listSub[] = new ControlPanelSubsection(
            /* I18N: Configuration option */I18N::translate('Displayed data'),
            array(new ControlPanelCheckbox(
                /* I18N: Configuration option */I18N::translate('Show link counts for shared places list'),
                /* I18N: Configuration option */ I18N::translate('Determining the link counts (linked individual/families) is expensive when assigning shared places via name, and therefore causes delays when the shared places list is displayed. It\'s recommended to only select this option if places are assigned via xref.'),
                'LINK_COUNTS',
                '0')));

    $sections = array();
    $sections[] = new ControlPanelSection(
            /* I18N: Configuration option */I18N::translate('General'),
            null,
            $generalSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Configuration option */I18N::translate('Facts and Events Tab Settings'),
            null,
            $factsAndEventsSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Configuration option */I18N::translate('Shared places list'),
            null,
            $listSub);

    return new ControlPanelPreferences($sections);
  }

}
