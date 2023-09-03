<?php
defined('BASEPATH') or exit('No direct script access allowed');


class PaymentModel extends CI_Model
{
	public function paymentList()
	{
		return $this->db->get('payment_lists')->result_object();
	}
	public function checkID($id)
	{
		return $this->db->get_where('data_santri', [
			'id_santri' => $id, 'status_santri' => 1
		])->row_object();
	}

	public function checkPaymentList($id, $type)
	{
		$checkPaymentList = $this->db->get_where('payment_lists', [
			'id' => $id
		])->num_rows();
		if ($checkPaymentList <= 0) {
			return [
				'status' => false
			];
		}

		$checkRate = $this->db->get_where('payment_list_rate', [
			'paymentlist_id' => $id, 'student_type' => $type
		])->row_object();
		if (!$checkRate) {
			return [
				'status' => false
			];
		}

		return [
			'status' => true,
			'nominal' => $checkRate->nominal
		];
	}

	public function checkPaymentPy01($id)
	{
		$period = $this->getPeriod();
		if (!$period) {
			return [
				'status' => false,
				'message' => 'Layanan pembayaran belum tersedia'
			];
		}
		$period = $period->tahun_periode;
		$check = $this->checkPayment($id, $period);
		if (!$check) {
			return [
				'status' => false,
				'message' => 'Pembayaran awal harus di pesantren Kebun Baru'
			];
		}
		$status = $check->status;
		if ($status === 'LUNAS') {
			return [
				'status' => false,
				'message' => 'Santri ini sudah melunasi pembayaran sebelumnya'
			];
		}

		return [
			'status' => true,
			'nominal' => $check->sisa
		];
	}

	public function store($id, $nominal, $idSantri, $noref)
	{
		$period = $this->getPeriod();
		$period = $period->tahun_periode;

		$this->db->insert('payment_emaal', [
			'student_id' => $idSantri,
			'nominal' => $nominal,
			'paymentlist_id' => $id,
			'noref' => $noref,
			'period' => $period,
			'created_at' => date('Y-m-d H:i:s'),
			'caption' => strtoupper($this->getCaption())
		]);
	}

	public function storePayment($nominal, $idSantri)
	{
		$period = $this->getPeriod();
		if ($period) {
			$period = $period->tahun_periode;
			$check = $this->checkPayment($idSantri, $period);
			if ($check) {
				$paymentId = $check->id;
				$getDetail = $this->db->get_where('payment_detail', [
					'payment_id' => $paymentId
				])->result_object();
				if ($getDetail) {
					$idPayment = $this->idGenerator();
					$this->db->insert('payment', [
						'id' => $idPayment,
						'created_at' => date('Y-m-d H:i:s'),
						'hijriah' => $this->getHijri(),
						'santri' => $idSantri,
						'tarif' => $check->tarif,
						'diskon_id' => $check->diskon_id,
						'diskon' => $check->diskon,
						'tagihan' => $check->sisa,
						'nominal' => $nominal,
						'sisa' => 0,
						'tipe' => $check->tipe,
						'periode' => $period,
						'nik' => $check->nik,
						'status' => 'LUNAS',
						'tahap' => 2,
						'user' => ($check->tipe == 2) ? 'MARIA NUR HAYATI' : 'ABD. KHOFI',
						'merchant' => 'EMAAL'
					]);

					$dataDetail = [];
					foreach ($getDetail as $item) {
						$dataDetail[] = [
							'payment_id' => $idPayment,
							'kode' => $item->kode,
							'nominal' => $item->nominal,
							'tipe' => $item->tipe,
							'kelas' => $item->kelas,
							'instansi' => $item->instansi,
							'periode' => $period,
							'tanggal' => $this->getHijri()
						];
					}
					$this->db->insert_batch('payment_detail', $dataDetail);
				}
			}
		}
	}

	public function getPeriod()
	{
		return $this->db->get('periode')->row_object();
	}

	public function checkPayment($id, $period)
	{
		return $this->db->order_by('created_at', 'DESC')->get_where('payment', [
			'santri' => $id, 'periode' => $period
		])->row_object();
	}

	public function idGenerator()
	{
		$tanggal = date('Y-m-d');
		$pecah = explode('-', $tanggal);
		$acak = mt_rand(1000, 9999);
		$id = $pecah[0] . $pecah[1] . $pecah[2] . $acak;
		return $id;
	}

	public function setDay()
	{
		$tgl1 = new DateTime('now');
		$jam  = $tgl1->format('H:m');

		$set = new DateTime('tomorrow');
		if ($jam > '18:00' and $jam < '23:59') {
			$set = new DateTime('tomorrow');
			return $set->format('Y-m-d');
		}

		return $tgl1->format('Y-m-d');
		// return $jam;
	}


	public function getHijri()
	{
		$tanggalMasehi = $this->setDay();
		$data = $this->db->get_where('kalender', ['masehi' => $tanggalMasehi])->row_object();

		if ($data) {
			return $data->hijri;
		}

		return '1441-01-01';
	}

	public function getCaption()
	{
		$hijri = $this->getHijri();

		$split = explode('-', $hijri);

		$month = $split[1];

		$months = [
			'01' => 'Muharram',
			'02' => 'Shafar',
			'03' => 'Rabi\'ul Awal',
			'04' => 'Rabi\'ul Akhir',
			'05' => 'Jumadal Ula',
			'06' => 'Jumadal Akhirah',
			'07' => 'Rajab',
			'08' => 'Sya\'ban',
			'09' => 'Ramadhan',
			'10' => 'Syawal',
			'11' => 'Dzul Qo\'dah',
			'12' => 'Dzul Hijjah',
		];

		return $months[$month];
	}
}




















