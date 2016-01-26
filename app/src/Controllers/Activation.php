<?php
namespace Membership\Controllers;

use Membership\Controllers;
use Slim\Exception\NotFoundException;

class Activation extends Controllers
{
    public function activate($request, $response, $args)
    {
        $db = $this->db;
        $q_activation_exist_count = $this->db->createQueryBuilder()
            ->select('COUNT(*) AS total_data')
            ->from('users_activations')
            ->where('user_id = :uid')
            ->andWhere('activation_key = :actkey')
            ->andWhere('deleted = :d')
            ->andWhere('expired_date > NOW()')
            ->setParameter(':uid', $args['uid'])
            ->setParameter(':actkey', $args['activation_key'])
            ->setParameter(':d', 'N')
            ->execute();

        $activation_exist_count = (int) $q_activation_exist_count->fetch()['total_data'];

        if ($activation_exist_count > 0) {
            $this->db->update('users', ['activated' => 'Y'], ['user_id' => $args['uid']]);

            $this->db->update('users_activations', ['deleted' => 'Y'], [
                'user_id' => $args['uid'],
                'activation_key' => $args['activation_key']
            ]);

            $this->flash->addMessage('success', 'Selamat! Account anda sudah aktif. Silahkan login...');
        } else {
            $this->flash->addMessage('error', 'Bad Request');
        }

        return $response->withRedirect($this->router->pathFor('membership-login'), 302);
    }

    public function reactivate($request, $response, $args)
    {
        $gcaptchaSiteKey = $this->settings['gcaptcha']['site_key'];
        $gcaptchaSecret = $this->settings['gcaptcha']['secret'];
        $gcaptchaEnable = $this->settings['gcaptcha']['enable'];

        if ($request->isPost()) {

            $validator = $this->validator;
            $validator->createInput($_POST);

            $validator->addNewRule('check_email_exist', function ($field, $value, array $params) use ($db) {
                $q_email_exist = $this->db->createQueryBuilder()
                    ->select('COUNT(*) AS total_data')
                    ->from('users')
                    ->where('email = :email')
                    ->andWhere('deleted = :d')
                    ->setParameter(':email', trim($_POST['email']))
                    ->setParameter(':d', 'N')
                    ->execute();

                $email_exist = (int) $q_email_exist->fetch()['total_data'];
                if ($email_exist > 0) {
                    return true;
                }

                return false;

            }, 'Tidak terdaftar!');

            $validator->rule('required', 'email');
            $validator->rule('check_email_exist', 'email');

            if ($validator->validate()) {
                //
            }
        }

        $this->view->addData([
            'page_title' => 'Membership',
            'sub_page_title' => 'Account Reactivation',
            'enable_captcha' => $gcaptchaEnable
        ], 'layouts::system');

        return $this->view->render(
            $response,
            'membership/account-reactivation',
            compact('gcaptcha_site_key', 'use_captcha')
        );
    }
}
