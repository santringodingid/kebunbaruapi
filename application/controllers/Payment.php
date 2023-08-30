<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Payment extends RestController
{

	public function __construct()
	{
		// Construct the parent class
		parent::__construct();
		$this->load->model('PaymentModel', 'pm');
	}

	public function index_get()
	{
		$result = $this->pm->paymentList();
		$this->response( [
			'status' => true,
			'data' => $result
		], RestController::HTTP_OK);
	}

	public function index_post()
	{
		$memberId = $this->post('member_id');
		$instansiCode = $this->post('instansi_code');
		$paymentListCode = $this->post('paymentlist_code');

		if (!$memberId || !$instansiCode || !$paymentListCode) {
			$this->response( [
				'status' => false,
				'error' => 'Field is required',
				'msg' => 'Member ID, Instansi Code or Payment List Code harus diisi'
			], RestController::HTTP_BAD_REQUEST);
		}

		$checkID = $this->pm->checkID($memberId);
		if (!$checkID) {
			$this->response( [
				'status' => false,
				'error' => 'Unknown data',
				'msg' => 'Tidak ada data dengan ID '.$memberId
			], RestController::HTTP_BAD_REQUEST);
		}
		$tipe = $checkID->tipe_santri;

		if ($paymentListCode === 'PY-01') {
			$checkRate = $this->pm->checkPaymentPy01($memberId);
			if (!$checkRate['status']) {
				$this->response( [
					'status' => false,
					'error' => 'Service not available',
					'msg' => $checkRate['message']
				], RestController::HTTP_BAD_REQUEST);
			}
		} else {
			$checkRate = $this->pm->checkPaymentList($paymentListCode, $tipe);
			if (!$checkRate['status']) {
				$this->response( [
					'status' => false,
					'error' => 'Service not available',
					'msg' => 'Pelayanan pembayaran belum tersedia'
				], RestController::HTTP_BAD_REQUEST);
			}
		}

		$this->response( [
			'status' => true,
			'member_id' => $checkID->id_santri,
			'member_name' => $checkID->nama_santri,
			'nominal' => $checkRate['nominal'],
			'payment' => 1
		], RestController::HTTP_OK);
	}

	public function store_post()
	{
		$paymentListCode = $this->post('paymentlist_code');
		$nominal = $this->post('nominal');
		$memberId = $this->input->post('member_id');

		$this->pm->store($paymentListCode, $nominal);
		if ($paymentListCode === 'PY-01') {
			$this->pm->storePayment($paymentListCode, $nominal, $memberId);
		}

		$this->response( [
			'status' => true,
		], 204);
	}
}