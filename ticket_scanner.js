jQuery(document).ready(function () {
    const { __, _x, _n, sprintf } = wp.i18n;
    let system = {code:0, nonce:'', data:null, redeemed_successfully:false, img_pfad:''};
	let myAjax;
    if (typeof IS_PRETTY_PERMALINK_ACTIVATED === "undefined") {
        IS_PRETTY_PERMALINK_ACTIVATED = false;
    }
    if (typeof Ajax_sasoEventtickets === "undefined") {
        myAjax = {
            url: '../../../../../wp-json/event-tickets-with-ticket-scanner/ticket/scanner/'
        };
        system.nonce = NONCE;
    } else {
        myAjax = Ajax_sasoEventtickets;
        system.nonce = myAjax.nonce;
        myAjax.url = myAjax._siteUrl+'/wp-json/event-tickets-with-ticket-scanner/ticket/scanner/';
        IS_PRETTY_PERMALINK_ACTIVATED = myAjax.IS_PRETTY_PERMALINK_ACTIVATED;
    }
    myAjax.rest_route = '/event-tickets-with-ticket-scanner/ticket/scanner/';
    myAjax.non_pretty_permalink_url = '../../../../../?rest_route=/event-tickets-with-ticket-scanner/ticket/scanner/';

    system.INPUTFIELD;
    var ticket_scanner_operating_option = {
        redeem_auto: false,
        distract_free: false
    };
    var loadingticket = false;
    var div_ticket_info_area = null;

    function onScanFailure(error) {
        // handle scan failure, usually better to ignore and keep scanning.
        // for example:
        //console.warn(`Code scan error = ${error}`);
      }
      var html5QrcodeScanner = null;

    function setRedeemImmediately() {
        ticket_scanner_operating_option.redeem_auto = !ticket_scanner_operating_option.redeem_auto;
    }
    function setDistractFree() {
        ticket_scanner_operating_option.distract_free = !ticket_scanner_operating_option.distract_free;
    }
    function onScanSuccess(decodedText, decodedResult) {
        if (loadingticket) return;
        loadingticket = true;

        // store setting to cookies / or browser storage
        _storeValue("ticketScannerCameraId", html5QrcodeScanner.persistedDataManager.data.lastUsedCameraId, 365);

        $("#ticket_scanner_info_area").html("<center>"+sprintf(/* translators: %s: ticket number */__("found %s", 'event-tickets-with-ticket-scanner'), decodedText)+'</center>');
        // handle the scanned code as you like, for example:
        //console.log(`Code matched = ${decodedText}`, decodedResult);
        $("#reader_output").html(__("...loading...", 'event-tickets-with-ticket-scanner'));
        //window.location.href = "?code="+encodeURIComponent(decodedText) + (ticket_scanner_operating_option.redeem_auto ? "&redeemauto=1" : "");
        retrieveTicket(decodedText);
        window.setTimeout(()=>{
            html5QrcodeScanner.clear().then((ignore) => {
                // QR Code scanning is stopped.
                // reload the page with the ticket info and redeem button
                //console.log("stop success");
            }).catch((err) => {
                // Stop failed, handle it.
                //console.log("stop failed");
            });
        }, 250);
      }

    function startScanner() {
        if (!ticket_scanner_operating_option.redeem_auto) jQuery("#ticket_scanner_info_area").html("");
        jQuery("#reader_output").html("");
        loadingticket = false;
        if (html5QrcodeScanner == null) {
            let options = { fps: 10, qrbox: {width: 250, height: 250} };
            let deviceId = _loadValue("ticketScannerCameraId");
            if (deviceId != "") {
                options.deviceId = {exact: deviceId}; // deviceId: { exact: cameraId}
            }
            html5QrcodeScanner = new Html5QrcodeScanner("reader",
                        options,
                        /* verbose= */ false);
        }
        //html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        html5QrcodeScanner.render(onScanSuccess);
        window.qrs = html5QrcodeScanner;
    }

    function showScanNextTicketButton() {
        let div = $('<div>');
        let btngrp = $('<div>').css("text-align", 'center').appendTo(div);
        $('<button>').html(__("Scan next Ticket", 'event-tickets-with-ticket-scanner')).on("click", e=>{
            clearAreas();
            startScanner();
        }).appendTo(btngrp);

        $('#reader').html(div);
    }
    function showScanOptions() {
        let div = $('<div>');
        let chkbox_redeem_imediately = $('<input type="checkbox">').on("click", e=>{
            setRedeemImmediately();
        }).appendTo(div);
        if (ticket_scanner_operating_option.redeem_auto && chkbox_redeem_imediately) chkbox_redeem_imediately.prop("checked", true);
        div.append(' Scan and Redeem immediately');
        div.append("<br>");

        let chkbox_distractfree = $('<input type="checkbox">').on("click", e=>{
            setDistractFree();
            if (ticket_scanner_operating_option.distract_free) {
                $('#ticket_info').css("display", "none");
            } else {
                $('#ticket_info').css("display", "block");
            }
        }).appendTo(div);
        div.append(' Hide ticket information');
        if (ticket_scanner_operating_option.distract_free && chkbox_distractfree) chkbox_distractfree.prop("checked", true);
        $('#reader_options').html(div);
    }

    function addMetaTag(name, content) {
        let head = document.getElementsByTagName("head")[0];
        let metaTags = head.getElementsByTagName("meta");
        //console.log(metaTags);
        let contains = false;
        for (let i=0;i<metaTags.length;i++) {
            let tag = metaTags[i];
            if (tag.name == name) {
                tag.content = content;
                contains = true;
                break;
            }
        }
        if (!contains) {
            let metaTag = document.createElement("meta");
            metaTag.name = name;
            metaTag.content = content;
            head.appendChild(metaTag);
        }
    }

    function _storeValue(name, wert, days) {
        if (window.JAVAJSBridge && window.JAVAJSBridge.setItem) window.JAVAJSBridge.setItem(name, wert);
        else setCookie(name, wert, days);
    }
    function _loadValue(name) {
        if (window.JAVAJSBridge && window.JAVAJSBridge.getItem) return window.JAVAJSBridge.getItem(name);
        return getCookie(name);
    }
    function setCookie(cname, cvalue, exdays) {
      var d = new Date();
      if (!exdays) exdays = 30;
      d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
      var expires = "expires="+d.toUTCString();
      document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }
    function getCookie(cname) {
      var name = cname + "=";
      var ca = document.cookie.split(';');
      for(var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') {
          c = c.substring(1);
        }
        if (c.indexOf(name) === 0) {
          return c.substring(name.length, c.length);
        }
      }
      return "";
    }
    function _getURLAndDateForAjax(action, myData, pcbf) {
        let _data = {};
        _data.action = action;
        //if (system.nonce != '') _data._wpnonce = system.nonce;
        //if (system.nonce != '') _data._ajax_nonce = system.nonce;
        _data.t = new Date().getTime();
        pcbf && pcbf();
        //if (myData) for(var key in myData) _data['data['+key+']'] = myData[key];
        if (myData) for(var key in myData) _data[key] = myData[key];
        if (system.nonce != '') {
            $.ajaxSetup({
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', system.nonce);
                },
            });
        }

        let url = myAjax.url;
        if (IS_PRETTY_PERMALINK_ACTIVATED == false) {
            url = myAjax.non_pretty_permalink_url;
        }
        url += action;
        return {url:url, data:_data};
    }
	function _downloadFile(action, myData, filenameToStore, cbf, ecbf, pcbf) {
        let call_data = _getURLAndDateForAjax(action, myData, pcbf);
        let params = "";
        for(let key in call_data.data) {
            params += key+"="+encodeURIComponent(call_data.data[key])+"&";
        }
		let url = call_data.url+'?'+params;
		//window.location.href = url;
		ajax_downloadFile(url, filenameToStore, cbf);
	}
    function ajax_downloadFile(urlToSend, fileName, cbf) {
		var req = new XMLHttpRequest();
		req.open("GET", urlToSend, true);
		req.responseType = "blob";
		req.onload = function (event) {
			var blob = req.response;
			//var fileName = req.getResponseHeader("X-fileName") //if you have the fileName header available
			var link=document.createElement('a');
			link.href=window.URL.createObjectURL(blob);
			link.download=fileName;
			link.click();
			cbf && cbf();
		};

		req.send();
	}
    function _makeGet(action, myData, cbf, ecbf, pcbf) {
        let call_data = _getURLAndDateForAjax(action, myData, pcbf);
        //non_pretty_permalink_url
        $.get( call_data.url, call_data.data, response=>{
            //if (response.data && response.data.nonce) system.nonce = response.data.nonce;
            if (!response.success) {
                if (ecbf) ecbf(response);
                else renderFatalError(response.data);
            } else {
                cbf && cbf(response.data);
            }
        }).always(jqXHR=>{
            if(jqXHR.status == 401 || jqXHR.status == 403) {
                renderFatalError(__("Access rights missing. Please login first.", 'event-tickets-with-ticket-scanner') + " "+(jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : '') );
            }
            if(jqXHR.status == 400) {
                renderFatalError(jqXHR.responseJSON.message);
            }
        });
    }
    function _makePost(action, myData, cbf, ecbf, pcbf) {
        let call_data = _getURLAndDateForAjax(action, myData, pcbf);
        $.post( call_data.url, call_data.data, response=>{
            //if (response.data && response.data.nonce) system.nonce = response.data.nonce;
            if (!response.success) {
                if (ecbf) ecbf(response);
                else renderFatalError(response.data);
            } else {
                cbf && cbf(response.data);
            }
        }).always(jqXHR=>{
            if(jqXHR.status == 401 || jqXHR.status == 403) {
                renderFatalError(__("Access rights missing. Please login first.", 'event-tickets-with-ticket-scanner') + " " + (jqXHR.responseJSON && jqXHR.responseJSON.message ? jqXHR.responseJSON.message : '') );
            }
            if(jqXHR.status == 400) {
                renderFatalError(jqXHR.responseJSON.message);
            }
        });
    }
    function _getSpinnerHTML() {
        return '<span class="lds-dual-ring"></span>';
    }
    function time(timezone_id) {
        let d = new Date();
        if (timezone_id && timezone_id.indexOf("/") > 0) {
            d = new Date(new Date().toLocaleString('en', {timeZone: timezone_id}));
        }
        return parseInt(d.getTime() / 1000);
    }
	function parseDate(str){
		if (!str) return null;
		return new Date(str.split(' ')[0].replace(/-/g,"/"));
	}
	function parseDateAndText(str, format) {
		return Date2Text(parseDate(str).getTime(), format);
	}
	function DateTime2Text(millisek) {
		return Date2Text(millisek, system.format_datetime ? system.format_datetime : "d.m.Y H:i");
	}
	function Date2Text(millisek, format, timezone_id) {
		if (!millisek)
			millisek = time(timezone_id);
		var d = new Date(millisek);
		if (!format)
			//format = system.format_date ? system.format_date : "%d.%m.%Y";
            format = system.format_date ? system.format_date : "d.m.Y";
			//format = "%d.%m.%Y %H:%i";
		var tage = [
            _x('Sun', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Mon', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Tue', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Wed', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Thu', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Fri', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Sat', 'cal', 'event-tickets-with-ticket-scanner')
        ];
		var monate = [
            _x('Jan', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Feb', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Mar', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Apr', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('May', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Jun', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Jul', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Aug', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Sep', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Oct', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Nov', 'cal', 'event-tickets-with-ticket-scanner'),
            _x('Dec', 'cal', 'event-tickets-with-ticket-scanner')
        ];
		var formate = {'d':d.getDate()<10?'0'+d.getDate():d.getDate(),
				'j':d.getDate(),'D':tage[d.getDay()],'w':d.getDate(),'m':d.getMonth()+1<10?'0'+(d.getMonth()+1):d.getMonth()+1,'M':monate[d.getMonth()],
				'n':d.getMonth()+1,'Y':d.getFullYear(),'y':d.getYear()>100?d.getYear().toString().substr(d.getYear().toString().length-2):d.getYear(),
				'H':d.getHours()<10?'0'+d.getHours():d.getHours(),'h':d.getHours()>12?d.getHours()-12:d.getHours(),
				'i':d.getMinutes()<10?'0'+d.getMinutes():d.getMinutes(),'s':d.getSeconds()<10?'0'+d.getSeconds():d.getSeconds()
				};
        for (var akey in formate) {
            //var rg = new RegExp('%'+akey, "g");
            var rg = new RegExp(akey, "g");
            format = format.replace(rg, formate[akey]);
        }
		return format;
	}
    function renderInfoBox(title, content) {
        let _options = {
            title: title,
            modal: true,
            minWidth: 400,
            minHeight: 200,
            buttons: [{text:_x('Ok', 'label', 'event-tickets-with-ticket-scanner'), click:function(){
                $(this).dialog(_x("Close", 'label', 'event-tickets-with-ticket-scanner'));
                $(this).html("");
            }}]
        };
        if (typeof content !== "string") content = JSON.stringify(content);
        let dlg = $('<div/>').html(content);
        dlg.dialog(_options);
        return dlg;
    }
    function renderFatalError(content) {
        return renderInfoBox('Error', content);
    }
    function basics_ermittelURLParameter() {
        var parawerte = {};
        var teile;
        if (window.location.search !== "") {
            teile = window.location.search.substring(1).split("&");
            for (var a=0;a<teile.length;a++)
            {
                var pos = teile[a].indexOf("=");
                if (pos < 0) {
                    parawerte[teile[a]] = true;
                } else {
                    var key = teile[a].substr(0,pos);
                    parawerte[key] = decodeURIComponent(teile[a].substr(pos+1));
                }
            }
        }
        return parawerte;
    }
    function clearAreas() {
        $('#ticket_info_btns').html('');
        $('#ticket_add_info').html('');
        $('#ticket_info').html('');
        $('#ticket_scanner_info_area').html('');
        $('#ticket_info_retrieved').html('');
    }
    function retrieveTicket(code, redeemed, cbf) {
        clearAreas();
        let div = $('#ticket_info').html(_getSpinnerHTML());
        div.css("display", "block");
        _makeGet('retrieve_ticket', {'code':code}, data=>{
            //console.log(data);
            if (ticket_scanner_operating_option.distract_free) {
                div.css("display", "none");
            }
            system.data = data;
            system.code = code; // falls per code überschrieben wurde

            system.format_datetime = data._ret.option_displayDateTimeFormat;

            //if (ticket_scanner_operating_option.redeem_auto) {
            //} else {
                displayTicketInfo(data);
                displayTicketRetrievedInfo(data);
                displayTicketAdditionalInfos(data);
            //}
            $("#reader_output").html("");

            if(ticket_scanner_operating_option.redeem_auto) {
                redeemTicket(code);
                //startScanner();
            } else {
                showScanNextTicketButton();
            }
            cbf && cbf();
        }, response=>{
            clearAreas();
            $("#reader_output").html('');
            $('#ticket_scanner_info_area').html('<h1 style="color:red;">'+response.data+'</h3>');
            showScanNextTicketButton();
            cbf && cbf();
        });
    }
    function isTicketExpired(ticketRetObject) {
        //debugger;
        //console.log(ticketRetObject);
        if (ticketRetObject.is_expired) return true;
        let t = time();
        if (ticketRetObject.timezone_id && ticketRetObject.timezone_id != "") {
            t = time(ticketRetObject.timezone_id );
        }
        if (ticketRetObject.ticket_end_date != "" && ticketRetObject.ticket_end_date_timestamp <= t) {
            return true;
        }
        return false;
    }
    function canTicketBeRedeemedNow(data) {
        if (data._ret._options.wcTicketDontAllowRedeemTicketBeforeStart) {
            let date = new Date(data._ret.redeem_allowed_from);
            if (new Date() < date) {
                return false;
            }
        }
        return true;
    }
    function displayTicketRetrievedInfo(data) {
        let div = $('<div>').css("text-align", "center");
        let metaObj = data.metaObj;
        if (!data._ret.is_paid) {
            $('<h4>').css('color', 'red').html(sprintf(/* translators: %s: order status */__('Ticket is NOT paid (%s).', 'label', 'event-tickets-with-ticket-scanner'),data._ret.order_status)).appendTo(div);
        } else {
            if (metaObj['wc_ticket']['redeemed_date'] != "") {
                $('<h4>').css('color', 'red').html(data._ret.msg_redeemed).appendTo(div);
                div.append(data._ret.redeemed_date_label+' '+metaObj['wc_ticket']['redeemed_date']);
            } else {
                if (data._ret.ticket_end_date == "" || data._ret.ticket_end_date_timestamp > time()) {
                    div.append('<div style="color:green;">'+data._ret.msg_ticket_valid+'</div>');
                } else {
                    if (isTicketExpired(data._ret)) {
                        div.append('<div style="color:red;">'+data._ret.msg_ticket_expired+'</div>');
                        div.append(data._ret.ticket_date_as_string);
                    }
                }
            }
            if (!canTicketBeRedeemedNow(data)) {
                div.append('<div style="color:red;">'+data._ret.msg_ticket_not_valid_yet+'</div>');
            }
        }
        $('#ticket_info_retrieved').html(div);
    }
    function displayTicketAdditionalInfos(data) {
        let div = $('<div style="width:50%;display:inline-block;">');
        if (data._ret.is_paid) {
            $('<div>').html('<b>'+__('Ticket paid', 'event-tickets-with-ticket-scanner')+'</b>').css("color", "green").appendTo(div);
        } else {
            $('<div>').html(__('Ticket NOT paid', 'event-tickets-with-ticket-scanner')).css("color", "red").appendTo(div);
        }
        if (data.metaObj.wc_ticket.redeemed_date != "") {
            $('<div>').html(__('Ticket redeemed', 'event-tickets-with-ticket-scanner')).appendTo(div);
        } else {
            $('<div>').html(__('Ticket not redeemed', 'event-tickets-with-ticket-scanner')).appendTo(div);
        }
        if (data._ret._options.displayConfirmedCounter) {
            $('<div>').html(sprintf(/* translators: %s: confirmed check counter */__('Confirmed status validation check counter: <b>%s</b>', 'event-tickets-with-ticket-scanner'), data.metaObj.confirmedCount)).appendTo(div);
        }
        $('<div>').html(sprintf(/* translators: %s: max redeem amount */__('Max Redeem Amount for this ticket: <b>%s</b>', 'event-tickets-with-ticket-scanner'), data._ret.max_redeem_amount)).appendTo(div);
        if(data._ret.max_redeem_amount > 1) {
            $('<div>').html(sprintf(/* translators: 1: redeemd tickets 2: max redeem */__('Redeem usage: <b>%1$d</b> of <b>%2$d</b>', 'event-tickets-with-ticket-scanner'), data.metaObj.wc_ticket.stats_redeemed.length, data._ret.max_redeem_amount)).appendTo(div);
        }
        let div2 = $('<div style="width:50%;display:inline-block;">');
        if (data.metaObj.woocommerce.creation_date != "") {
           div2.append('<div>'+sprintf(/* translators: %s: date */__('Bought at %s', 'event-tickets-with-ticket-scanner'), DateTime2Text(new Date(data.metaObj.woocommerce.creation_date).getTime()))+'</div>');
        }
        if (typeof data.metaObj.expiration != "undefined") {
            if (data.metaObj.expiration.date != "") {
                div2.append('<div>'+sprintf(/* translators: %s: date */__('Expiration at %s', 'event-tickets-with-ticket-scanner'), DateTime2Text(new Date(data.metaObj.expiration.date).getTime()))+'</div>');
            } else {
                let date_expiration_ms = new Date(data.metaObj.woocommerce.creation_date).getTime();
                date_expiration_ms += data.metaObj.expiration.days * 24 * 3600 * 1000;
                let exp_text = data.metaObj.expiration.days > 0 ? sprintf(/* translators: 1: days 2: date */__('Expires after %1$d days (%2$s)', 'event-tickets-with-ticket-scanner'), data.metaObj.expiration.days, DateTime2Text( date_expiration_ms )) : '-';
                div2.append('<div>'+exp_text+'</div>');
            }
        }
        let content = "";
        if (ticket_scanner_operating_option.distract_free) {
            content = '<center>'+system.code+'</center>';
        }
        $('#ticket_add_info').html(content).append( $('<div style="padding-top:10px;width:100%;">').append(div).append(div2) );
    }
    function redeemTicket(code) {
        clearAreas();
        system.redeemed_successfully = false;
        $("#reader_output").html(__("start redeem ticket...loading..."));
        $('#ticket_scanner_info_area').html(_getSpinnerHTML());
        _makeGet('redeem_ticket', {'code':code}, data=>{
            system.redeemed_successfully = data.redeem_successfully;
            displayTicketRedeemedInfo(data);
            if(ticket_scanner_operating_option.redeem_auto) {
                startScanner();
            } else {
                retrieveTicket(code, true);
            }
            system.INPUTFIELD.focus();
            system.INPUTFIELD.select();
        }, response=>{
            clearAreas();
            $("#reader_output").html('');
            $('#ticket_scanner_info_area').html('<h1 style="color:red;">'+response.data+'</h3>');
            if(ticket_scanner_operating_option.redeem_auto) {
                startScanner();
            } else {
                showScanNextTicketButton();
            }
            system.INPUTFIELD.focus();
            system.INPUTFIELD.select();
        });
    }
    function displayTicketRedeemedInfo(data) {
        // zeige retrieved info an
        $('#ticket_scanner_info_area').html('<center>'+system.code+'</center>');
        if (system.redeemed_successfully) {
            $('#ticket_scanner_info_area').append('<h3 style="color:green;text-align:center;">'+__('TICKET OK - Redeemed', 'event-tickets-with-ticket-scanner')+'</h3>');
            $('#ticket_scanner_info_area').append('<p style="text-align:center;color:green"><img src="'+system.img_pfad+'button_ok.png"><br><b>'+__('Successfully redeemed', 'event-tickets-with-ticket-scanner')+'</b></p>');
        } else {
            $('#ticket_scanner_info_area').append('<h3 style="color:red;text-align:center;">'+__('TICKET NOT REDEEMED - see reason below', 'event-tickets-with-ticket-scanner')+'</h3>');
            $('#ticket_scanner_info_area').append('<p style="text-align:center;color:red;"><img src="'+system.img_pfad+'button_cancel.png"><br><b>'+__('Failed to redeem', 'event-tickets-with-ticket-scanner')+'</b></p>');
        }
    }
    function displayTicketInfoButtons(data) {
        let div = $('<div>').css('text-align', 'center');
        if (!data._ret.is_paid) {
            $('<h4>').css('color', 'red').html(sprintf(/* translators: %s: order status */__('Ticket is NOT paid (%s).', 'event-tickets-with-ticket-scanner'), data._ret.order_status)).appendTo(div);
        }
        $('<button>').html('Reload').appendTo(div).on('click', e=>{
            retrieveTicket(system.code);
        });
        let btn_redeem = $('<button>').html(_x('Redeem Ticket', 'label', 'event-tickets-with-ticket-scanner')).css("background-color", 'gray').css('color', 'white').prop("disabled", true).appendTo(div).on('click', e=>{
            redeemTicket(system.code);
        });
        $('<button>').html(_x('PDF', 'label', 'event-tickets-with-ticket-scanner')).appendTo(div).on('click', e=>{
            window.open(data.metaObj['wc_ticket']['_url']+'?pdf', '_blank');
        });
        $('<button>').html(_x('Badge', 'label', 'event-tickets-with-ticket-scanner')).appendTo(div).on('click', e=>{
            _downloadFile('downloadPDFTicketBadge', {'code':data.code}, "eventticket_badge_"+data.code+".pdf");
            return false;
        });
        let allow_redeem = false;
        if (data._ret.allow_redeem_only_paid) {
            if (data._ret.is_paid) {
                allow_redeem = true;
            }
        } else {
            allow_redeem = true;
        }
        if (allow_redeem) {
            if ( isTicketExpired(data._ret) ) {
                allow_redeem = false;
            } else { // noch gültig oder kein end-datum
                if (data.metaObj['wc_ticket']['redeemed_date'] != "") {
                        allow_redeem = false;
                }
                if (data._ret.max_redeem_amount > 1 && data.metaObj.wc_ticket.stats_redeemed.length < data._ret.max_redeem_amount) {
                    allow_redeem = true;
                }
            }
            if (allow_redeem) {
                allow_redeem = canTicketBeRedeemedNow(data);
            }
        }
        if (allow_redeem) {
            btn_redeem.prop("disabled", false).css('background-color','green');
        }

        div.append(displayRedeemedTicketsInfo(data));
        $('#ticket_info_btns').html(div);
    }
    function displayRedeemedTicketsInfo(data) {
        if (!data._ret.tickets_redeemed_show) return "";
        let div = $('<div style="padding:10px;">').css('text-align', 'center');
        $('<h5>').html(sprintf(/* translators: %d: amount redeemed tickets */__('%d tickets of this event (product) redeemed already', 'event-tickets-with-ticket-scanner'), data._ret.tickets_redeemed)).appendTo(div);
        return div;
    }
    function displayTicketInfo(data) {
        //console.log(data);
        //console.log(data._ret);
        // zeige ticket info an
        let codeObj = data;
        let metaObj = data.metaObj;
        let ret = data._ret;
        let div = $('<div>').css('padding', '10px');
        let border_color = 'green';
        if (isTicketExpired(data._ret)) {
            border_color = 'orange';
        }
        if (metaObj['wc_ticket']['redeemed_date'] != "") {
            border_color = 'red';
        }
        div.css("border", "1px solid "+border_color);

        $('<h3 style="text-align:center;">').html(ret.ticket_heading).appendTo(div);
        $('<h4>').html(ret.ticket_title).appendTo(div);
        if (ret.ticket_sub_title != "") {
            $('<p>').html(ret.ticket_sub_title).appendTo(div);
        }
        $('<p>').html(ret.ticket_date_as_string).appendTo(div);
        if (ret.ticket_location != "") {
            $('<p>').html(ret.ticket_location_label+' '+ret.ticket_location).appendTo(div);
        }
        if (ret.short_desc != "") {
            div.append(ret.short_desc).append('<br>');
        }
        if (ret.ticket_info != "") {
            $('<p>').html(ret.ticket_info).appendTo(div);
        }
        if (ret.cst_label != "") {
            $('<p>').html('<b>'+ret.cst_label+'</b><br>'+ret.cst_billing_address+'<br>').appendTo(div);
        }
        if (ret.payment_label != "") {
            let date_order_paid = ret.payment_paid_at;
            let date_order_complete = null;
            if (ret.payment_completed_at !== "undefined") {
                date_order_complete = ret.payment_completed_at;
            }
            let p = $('<p>').appendTo(div);
            p.append('<b>'+ret.payment_label+'</b><br>');
            p.append("Order status: "+ret.order_status+"<br>");
            p.append(ret.payment_paid_at_label+' ');
            p.append('<b>'+date_order_paid+'</b><br>');
            if (date_order_complete != null) {
                p.append(ret.payment_completed_at_label+' ');
                p.append('<b>'+date_order_complete+'</b><br>');
            }
            p.append(ret.payment_method_label);
            if (ret.payment_method != "") {
                p.append(' '+ret.payment_method+' '+ret.payment_trx_id);
            }
            p.append('<br>');
            if (ret.coupon != "") {
                p.append(ret.coupon_label+' <b>'+ret.coupon+'</b><br>');
            }
        }
        if (ret.ticket_amount_label != "") {
            $('<p>').html(ret.ticket_amount_label).appendTo(div);
        }
        let p = $('<p>').html(ret.ticket_label+' <b>'+codeObj['code_display']+'</b><br>').appendTo(div);
        p.append(ret.paid_price_label+' <b>'+ret.paid_price_as_string+'</b>');
        if (ret.product_price != ret.paid_price) {
            p.append(' <b>('+ret.product_price_label+' '+ret.product_price_as_string+')</b>');
        }
        $('<p style="text-align:center;">').html(system.code).appendTo(div);

        div_ticket_info_area = $('#ticket_info').html(div);
        displayTicketInfoButtons(data);
    }
    function addInputField() {
        let div = $('<div>').css('text-align', 'center');
        $('<label for="barcode_scanner_input" class="form-label" style="color:#837878">').html(__('For QR code barcode scanner', 'event-tickets-with-ticket-scanner')).appendTo(div);
        $('<br>').appendTo(div);
        let inputField = $('<input style="width:70%;" name="barcode_scanner_input" placeholder="'+_x('Type in the ticket number and hit ENTER (optional to scanning)', 'attr', 'event-tickets-with-ticket-scanner')+'" type="text">')
            .appendTo(div)
            .on("change", ()=>{
                retrieveTicket(inputField.val().trim(), false, ()=>{
                    inputField.focus();
                    inputField.select();
                });
            })
            .on("keypress", event=>{
                if (event.key === "Enter") {
                    event.preventDefault();
                    retrieveTicket(inputField.val().trim(), false, ()=>{
                        inputField.focus();
                        inputField.select();
                    });
                }
            });
        system.INPUTFIELD = div;
        $("#ticket_scanner_info_area").parent().prepend(system.INPUTFIELD);
    }
    function starten() {
        addMetaTag("viewport", "width=device-width, initial-scale=1");
        $ = jQuery;
        $('#reader').html(_getSpinnerHTML());
        _makeGet('ping', [], data=>{
            system.data = data; // initialer daten empfang mit options
            system.img_pfad = data.img_pfad;
            system.PARA = basics_ermittelURLParameter();
            if (system.PARA.redeemauto) {
                ticket_scanner_operating_option.redeem_auto = true;
            }
            if (system.PARA.distractfree) {
                ticket_scanner_operating_option.distract_free = true;
            }
            addInputField();
            showScanOptions();
            if (system.PARA.code) {
                system.code = system.PARA.code;
                retrieveTicket(system.code);
            } else {
                startScanner();
            }
        });
    }
    var $;
    //window.onload = starten;
    starten();

} );



