jQuery(document).ready(function($) {
	'use strict';

	var countdownInterval = null;

	// Open Modal
	$('#socc-view-details').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var orderId = $('.socc-meta-box-content').data('order-id');

		if ($btn.hasClass('disabled')) {
			return;
		}

		$btn.addClass('disabled').text('Loading...');

		$.ajax({
			url: socc_params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'socc_view_details',
				order_id: orderId,
				nonce: socc_params.nonce
			},
			success: function(response) {
				$btn.removeClass('disabled').text('View Card Details');
				if (response.success) {
					var data = response.data;
					var tableHtml = '<table class="socc-modal-table">';
					tableHtml += '<tr><th>Cardholder Name</th><td>' + $('<div>').text(data.holder).html() + '</td></tr>';
					tableHtml += '<tr><th>Card Number</th><td style="letter-spacing: 1px;">' + $('<div>').text(data.number).html() + '</td></tr>';
					tableHtml += '<tr><th>Expiry Date</th><td>' + $('<div>').text(data.expiry).html() + '</td></tr>';
					tableHtml += '<tr><th>CVV/CVC</th><td>' + $('<div>').text(data.cvv).html() + '</td></tr>';
					tableHtml += '</table>';

					$('#socc-modal-data').html(tableHtml);
					$('#socc-modal').fadeIn(200);

					startCountdown();
				} else {
					alert(response.data || socc_params.strings.decrypt_error);
				}
			},
			error: function() {
				$btn.removeClass('disabled').text('View Card Details');
				alert(socc_params.strings.decrypt_error);
			}
		});
	});

	// Close Modal
	$('.socc-modal-close').on('click', function() {
		closeModal();
	});

	$(window).on('click', function(e) {
		if ($(e.target).is('#socc-modal')) {
			closeModal();
		}
	});

	function closeModal() {
		$('#socc-modal').fadeOut(200, function() {
			$('#socc-modal-data').empty();
		});
		clearInterval(countdownInterval);
	}

	function startCountdown() {
		var count = 60;
		$('#socc-countdown-num').text(count);
		clearInterval(countdownInterval);

		countdownInterval = setInterval(function() {
			count--;
			$('#socc-countdown-num').text(count);
			if (count <= 0) {
				closeModal();
			}
		}, 1000);
	}

	// Purge Details
	$('#socc-purge-details').on('click', function(e) {
		e.preventDefault();
		if (!confirm(socc_params.strings.confirm_purge)) {
			return;
		}

		var $btn = $(this);
		var orderId = $('.socc-meta-box-content').data('order-id');

		if ($btn.hasClass('disabled')) {
			return;
		}

		$btn.addClass('disabled').text('Purging...');

		$.ajax({
			url: socc_params.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'socc_purge_details',
				order_id: orderId,
				nonce: socc_params.nonce
			},
			success: function(response) {
				if (response.success) {
					var $content = $('.socc-meta-box-content');
					$content.find('.socc-details-table').remove();
					$content.find('.socc-actions').remove();
					
					var statusHtml = '<p class="socc-purged-status" style="color: #b32d2e; font-weight: bold;">' + response.data.message + '</p>';
					$content.prepend(statusHtml);

					location.reload();
				} else {
					$btn.removeClass('disabled').text('Purge Card Data');
					alert(response.data || socc_params.strings.purge_error);
				}
			},
			error: function() {
				$btn.removeClass('disabled').text('Purge Card Data');
				alert(socc_params.strings.purge_error);
			}
		});
	});
});
