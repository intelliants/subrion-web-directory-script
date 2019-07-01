<?php
/******************************************************************************
 *
 * Subrion Web Directory Script
 * Copyright (C) 2018 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion Web Directory Script.
 *
 * This program is a commercial software and any kind of using it must agree
 * to the license, see <https://subrion.pro/license.html>.
 *
 * This copyright notice may not be removed from the software source without
 * the permission of Subrion respective owners.
 *
 *
 * @link https://subrion.pro/product/directory.html
 *
 ******************************************************************************/

$iaCateg = $iaCore->factoryItem('categ');

if (iaView::REQUEST_JSON == $iaView->getRequestType()) {
    if (1 == count($iaCore->requestPath) && 'tree' == $iaCore->requestPath[0]) {
        $iaView->assign($iaCateg->getJsonTree($_GET));
    }
}

if (iaView::REQUEST_HTML == $iaView->getRequestType()) {
    $iaField = $iaCore->factory('field');
    $iaUtil = $iaCore->factory('util');

    $iaListing = $iaCore->factoryItem('listing');

    iaBreadcrumb::replace(iaLanguage::get(IA_CURRENT_MODULE),
        $iaCore->factory('page', iaCore::FRONT)->getUrlByName('directory_home'), 2);

    switch ($pageAction) {
        case iaCore::ACTION_ADD:
            $category = $iaCateg->getRoot();
            $listing = ['category_id' => $category['id']];

            break;
        case iaCore::ACTION_EDIT:
        case iaCore::ACTION_DELETE:
            $listingId = (int)end($iaCore->requestPath);

            if (empty($listingId)) {
                return iaView::errorPage(iaView::ERROR_NOT_FOUND);
            } else {
                $listing = $iaListing->getById($listingId);
                if (empty($listing)) {
                    return iaView::errorPage(iaView::ERROR_NOT_FOUND);
                } elseif (!iaUsers::hasIdentity() || $listing['member_id'] != iaUsers::getIdentity()->id) {
                    return iaView::accessDenied();
                }

                $category = $iaCateg->getById($listing['category_id']);
            }

            if (iaCore::ACTION_DELETE == $pageAction) {
                $iaView->disableLayout();

                $iaListing->delete($listing)
                    ? iaUtil::redirect(iaLanguage::get('wait_redirect'), iaLanguage::get('listing_removed'),
                    $iaCore->factory('page', iaCore::FRONT)->getUrlByName('my_listings'))
                    : iaUtil::redirect(iaLanguage::get('wait_redirect'), iaLanguage::get('cant_remove_listing'),
                    $iaListing->url('view', $listing));
            }
    }

    $where = ''; // used to ignore some fields
    if (!$iaCore->get('reciprocal_check')) {
        $where .= " f.`name` != 'reciprocal_url' ";
    }

    $iaPlan = $iaCore->factory('plan');
    $plans = $iaPlan->getPlans($iaListing->getItemName());
    $iaView->assign('plans', $plans);

    if (isset($_POST['data-listing'])) {
        $item = false;
        $error = false;
        $plan = false;
        $messages = [];

        list($item, $error, $messages) = $iaField->parsePost($iaListing->getItemName(), $listing);

        if (!iaUsers::hasIdentity() && !iaValidate::isCaptchaValid()) {
            $error = true;
            $messages[] = iaLanguage::get('confirmation_code_incorrect');
        }

        if (!empty($item['url']) && !iaValidate::isUrl($item['url'])) {
            if (iaValidate::isUrl($item['url'], false)) {
                $item['url'] = 'http://' . $item['url'];
            } else {
                $error = true;
                $messages[] = iaLanguage::get('error_url');
            }
        }

        if (!empty($item['email']) && !iaValidate::isEmail($item['email'])) {
            $error = true;
            $messages[] = iaLanguage::get('error_email_incorrect');
        } elseif (!iaUsers::hasIdentity() && (!isset($item['email']) || empty($item['email']))) {
            $error = true;
            $messages[] = iaLanguage::getf('field_is_empty', ['field' => iaLanguage::get('email')]);
        }

        $item['ip'] = $iaUtil->getIp();
        $item['member_id'] = 0;

        if (iaUsers::hasIdentity()) {
            $item['member_id'] = iaUsers::getIdentity()->id;
        } elseif ($iaCore->get('listing_tie_to_member')) {
            $iaUsers = $iaCore->factory('users');
            if ($member = $iaUsers->getInfo($item['email'], 'email')) {
                $item['member_id'] = $member['id'];
            }
        }

        $item['category_id'] = (int)$_POST['tree_id'];
        $item['status'] = $iaCore->get('listing_auto_approval') ? iaCore::STATUS_ACTIVE : iaCore::STATUS_APPROVAL;
        $item['slug'] = iaSanitize::alias($_POST['title'][$iaView->language]);

        $category = $iaCateg->getById($item['category_id']);

        $planOptions = $this->iaDb->row('value', 'plan_id = "' . $_POST['plan_id'] . '" ', 'payment_plans_options_values');
        $crossed_links = substr_count($_POST['crossed_links'], ',') + 1;
        if(empty($_POST['plan_id'])) {
            $planOptions['value'] = $iaCore->get('listing_crossed_limit');
        }

        if($planOptions['value'] < $crossed_links) {
            $error = true;
            $messages[] = iaLanguage::get('error_crossed_links_listing');
        }

        if (!$category) {
            $error = true;
            $messages[] = iaLanguage::get('invalid_category');
        } elseif ($category['locked']) {
            $error = true;
            $messages[] = iaLanguage::get('error_locked_category');
        }

        if (iaCore::ACTION_ADD == $pageAction) {
            if (!$iaListing->isSubmissionAllowed($item['member_id'])) {
                $error = true;
                $messages[] = iaLanguage::get('limit_is_exceeded');
            }
        }
        if (!$error) {
            // get domain name and URL status
            $item['domain'] = $iaListing->getDomain($item['url']);

            if (iaValidate::isUrl($item['url'])) {
                // check alexa
                if ($iaCore->get('directory_enable_alexarank')) {
                    include IA_MODULES . $iaCore->modulesData['directory']['name'] . IA_DS . 'includes/alexarank.inc.php';
                    $iaAlexaRank = new iaAlexaRank();

                    if ($alexaData = $iaAlexaRank->getAlexa($item['domain'])) {
                        $data['alexa_rank'] = $alexaData['rank'];
                    }
                }
            }

            // check if listing with specified field already exists
            if (iaCore::ACTION_ADD == $pageAction && $iaCore->get('directory_duplicate_check')) {
                if ($field = $iaListing->checkDuplicateListings($item)) {
                    $error = true;
                    $messages[] = iaLanguage::getf('error_duplicate_field', ['field' => $field]);
                }
            }
        }

        if (!$error) {
            $iaCore->startHook('phpDirectoryBeforeListingSubmit', ['item' => &$item]);

            if (iaCore::ACTION_ADD == $pageAction) {
                $id = $iaListing->insert($item);

                if (!$id) {
                    $error = true;
                    $messages[] = iaLanguage::get('error_add_listing');
                } else {
                    $item['id'] = $id;
                    $messages[] = ($item['status'] == iaCore::STATUS_ACTIVE ? iaLanguage::get('listing_added') : iaLanguage::get('listing_added_waiting'));
                }
            } else {
                $id = $listing['id'];
                $result = $iaListing->update($item, $id);

                if (!$result) {
                    $error = true;
                    $messages[] = iaLanguage::get('error_update_listing');
                } else {
                    $messages[] = ($item['status'] == iaCore::STATUS_ACTIVE ? iaLanguage::get('listing_updated') : iaLanguage::get('listing_updated_waiting'));
                }
            }
        } else {
            $listing = $item; // keep user input
        }

        if (!$error) {
            $item['slug'] = $category['slug'];
            $url = (iaCore::STATUS_ACTIVE == $item['status'] ||
                (iaUsers::hasIdentity() && iaCore::STATUS_APPROVAL == $item['status']))
                ? $iaListing->url('view',
                    $iaListing->getById($id)) : $iaCore->modulesData[$iaListing->getModuleName()]['url'];

            // if plan is chosen
            if (!empty($_POST['plan_id']) && $_POST['plan_id'] != $listing['sponsored_plan_id']) {
                $plan = $iaPlan->getById((int)$_POST['plan_id']);

                if ($plan['cost'] > 0) {
                    $url = $iaPlan->prePayment($iaListing->getItemName(), $item, $plan['id'], $url);
                } elseif (iaCore::STATUS_ACTIVE == $item['status']) {
                    $iaTransaction = $iaCore->factory('transaction');
                    $transactionId = $iaTransaction->create(null, 0, $iaListing->getItemName(), $item, '',
                        (int)$_POST['plan_id'], true);
                    $transaction = $iaTransaction->getBy('sec_key', $transactionId);
                    $iaPlan->setPaid($transaction);
                }
            }

            $iaCore->startHook('phpAddItemAfterAll', [
                'type' => iaCore::ADMIN,
                'listing' => $id,
                'item' => $iaListing->getItemName(),
                'data' => $item,
                'old' => $listing
            ]);

            if (iaCore::ACTION_EDIT != $pageAction || $plan) {
                $iaView->setMessages($messages, iaView::SUCCESS);
                iaUtil::go_to($url);
            }
        }

        $iaView->setMessages($messages, $error ? iaView::ERROR : iaView::SUCCESS);

        $listing = array_merge((array)$listing, $item);
    }

    if (iaCore::ACTION_EDIT == $pageAction) {
        $iaCore->factory('item')->setItemTools([
            'id' => 'action-visit',
            'title' => iaLanguage::get('view'),
            'attributes' => ['href' => $iaListing->url('view', $listing)]
        ]);

        $crossedCategories = isset($_POST['crossed_links'])
            ? $iaCateg->getCrossedByIds($_POST['crossed_links'])
            : $iaCateg->getCrossedByListingId($listing['id']);

        $iaView->assign('crossed', $crossedCategories);
    } elseif (isset($_GET['category']) && is_numeric($_GET['category'])) {
        $category = $iaCateg->getById($_GET['category']);
    }

    $listing['item'] = $iaListing->getItemName();

    $sections = $iaField->getTabs($iaListing->getItemName(), $listing);

    $iaView->assign('sections', $sections);
    $iaView->assign('item', $listing);
    $iaView->assign('tree', $iaCateg->getTreeVars($category['id'], $category['title']));

    $iaView->display('manage');
}
