<?php

namespace App\Xdag;

use App\Xdag\Exceptions\{XdagException, XdagBlockNotFoundException, XdagBlockNotLoadedException};

class Block
{
	protected $properties = [], $transactions = [], $addresses = [], $payouts = [];

	public function isLoaded()
	{
		return isset($this->properties['hash']);
	}

	public function getProperties()
	{
		return $this->properties;
	}

	public function getProperty($name)
	{
		return $this->properties[$name] ?? null;
	}

	public function getTransactions()
	{
		return $this->transactions;
	}

	public function getAddresses()
	{
		return $this->addresses;
	}

	public function getPayouts()
	{
		return $this->payouts;
	}

	public function getPayoutsSum()
	{
		$sum = 0;
		foreach ($this->payouts as $payout)
			$sum += $payout['amount'];

		return $sum;
	}

	public function isMainBlock()
	{
		return isset($this->properties['flags']) && $this->properties['flags'] == '1f';
	}

	public function hasEarning(&$amount = null)
	{
		if (!count($this->addresses))
			return false;

		if (!$this->isMainBlock())
			return false;

		foreach ($this->addresses as $address) {
			if ($address['direction'] == 'earning') {
				$amount = $address['amount'];
				return true;
			}
		}

		return false;
	}

	public function isPaidOut()
	{
		return $this->hasEarning($amount) && isset($this->properties['balance']) && $this->properties['balance'] < $amount;
	}

	public function persist($partial = true)
	{
		if (!$this->isLoaded())
			throw new XdagBlockNotLoadedException;

		$file = __ROOT__ . '/storage/blocks/' . $this->properties['hash'] . '.json';
		$data = [
			'properties' => $this->properties,
			'transactions' => $this->transactions,
			'addresses' => $this->addresses,
			'payouts' => $this->payouts,
		];

		if ($partial)
			unset($data['transactions'], $data['addresses']);

		$data = @json_encode($data, JSON_PRETTY_PRINT);

		if ($data === false || @file_put_contents($file, $data) === false)
			throw new XdagException('Unable to persist block: ' . $this->properties['hash']);
	}

	public function load($hash)
	{
		$file = __ROOT__ . '/storage/blocks/' . $hash . '.json';
		$data = @file_get_contents($file);

		if ($data === false)
			throw new XdagException('Unable to load block: ' . $hash);

		$structure = @json_decode($data, true);
		if ($structure === false)
			throw new XdagException('Unable to parse stored block: ' . $hash);

		$this->properties = $structure['properties'];
		$this->transactions = $structure['transactions'] ?? [];
		$this->addresses = $structure['addresses'] ?? [];
		$this->payouts = $structure['payouts'];

		return $data;
	}

	public function remove()
	{
		if (!$this->isLoaded())
			throw new XdagBlockNotLoadedException;

		$file = __ROOT__ . '/storage/blocks/' . $this->properties['hash'] . '.json';

		if (!@unlink($file))
			throw new XdagException('Unable to remove block: ' . $this->properties['hash']);

		$this->clean();
	}

	public function parse($generator, callable $get_block)
	{
		$this->clean();
		$state = 'properties';

		foreach ($generator as $line) {
			if ($state == 'properties') {
				if (stripos($line, 'Block is not found') !== false)
					throw new XdagBlockNotFoundException;

				if (stripos($line, 'Block as transaction: details')) {
					$state = 'transactions';
					continue;
				}

				if (preg_match('/\s*(.*): (.*)/', $line, $matches)) {
					$key = strtolower(trim($matches[1]));
					$value = strtolower(trim($matches[2]));

					if ($key == 'balance') {
						$this->properties['balance_address'] = current($balance = explode(' ', $matches[2]));
						$value = end($balance);
					}

					$this->properties[$key] = $value;
				}
			} else if ($state == 'transactions') {
				if (stripos($line, 'block as address: details')) {
					$state = 'addresses';
					continue;
				}

				if (preg_match('/^\s*(fee|input|output): ([a-zA-Z0-9\/+]{32})\s+([0-9]+\.[0-9]+)$/si', $line, $matches)) {
					list(, $direction, $address, $amount) = $matches;
					$this->transactions[] = [
						'direction' => strtolower(trim($direction)),
						'address' => trim($address),
						'amount' => strtolower(trim($amount)),
					];
				}
			} else if ($state == 'addresses') {
				if (preg_match('/^\s*(fee|input|output|earning): ([a-zA-Z0-9\/+]{32})\s+([0-9]+\.[0-9]+)\s+([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3})(.*)$/si', $line, $matches)) {
					list(, $direction, $address, $amount, $time, $remark) = $matches;
					$this->addresses[] = [
						'direction' => strtolower(trim($direction)),
						'address' => trim($address),
						'amount' => strtolower(trim($amount)),
						'time' => strtolower(trim($time)),
						'remark' => trim($remark),
					];
				}
			}
		}

		if ($state !== 'addresses')
			throw new XdagException('Invalid block markup.');

		// parse payouts, if block is main and is paid out
		if ($this->isPaidOut()) {
			foreach ($this->addresses as $address) {
				if ($address['direction'] != 'output')
					continue;

				try {
					$block = $get_block($address['address']);
				} catch (XdagBlockNotFoundException $ex) {
					continue;
				}

				// collect payouts to miners
				$payouts = [];
				foreach ($block->getTransactions() as $transaction) {
					if ($transaction['direction'] == 'input' && $transaction['address'] !== $this->properties['balance_address']) {
						// don't support address blocks that paid out the pool wallet
						// some pool wallet payouts will be imported, when just exactly one
						// pool block was paid out in an address block,
						// as those payouts are indistinguishable from normal miner payouts
						$payouts = [];
						break;
					}

					if ($transaction['direction'] != 'output')
						continue;

					$payouts[] = [
						'address' => trim($transaction['address']),
						'time' => $address['time'],
						'amount' => strtolower(trim($transaction['amount'])),
					];
				}

				$this->payouts = array_merge($this->payouts, $payouts);
			}

			// persist fully processed main block
			$this->persist();
		}
	}

	protected function clean()
	{
		$this->properties = $this->transactions = $this->addresses = $this->payouts = [];
	}
}
