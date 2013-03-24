// https://github.com/bryanwoods/autolink-js
var g=[].slice;String.prototype.autoLink=function(){var e,b,d,a,c,f;c=1<=arguments.length?g.call(arguments,0):[];d="";a=c[0];f=/(^|\s)(\b(https?|ftp):\/\/[\-A-Z0-9+\u0026@#\/%?=~_|!:,.;]*[\-A-Z0-9+\u0026@#\/%=~_|])/gi;if(!(0<c.length))return this.replace(f,"$1<a href='$2'>$2</a>");null!=a.callback&&"function"===typeof a.callback&&(e=a.callback,delete a.callback);for(b in a)c=a[b],d+=" "+b+"='"+c+"'";return this.replace(f,function(a,c,b){a=e&&e(b);return""+c+(a||"<a href='"+b+"'"+d+">"+b+"</a>")})};

var crmx = {
	config: {
		sitename: '',
		username: ''
	},
	form: {},
	people: {},

	timer: false,

	// Methods to load stuff
	load: {

		/**
		 * load.form
		 * Generates the HTML form (without values)
		 * @params (object)
		 */
		form: function(form) {"use strict";
			$('#form').html('');
			for (var i in form) {
				// detect field type and generate its html
				if (form[i].type==='select') {
					form[i].html = '<select id="'+form[i].name+'">';
						for (var j in form[i].list) {
							form[i].html += '<option>'+form[i].list[j]+'</option>';

							if (form[i].searchable && form[i].list[j]!=='-') {
								$('#searchable').append('<li><a href="#" data-search=\'"'+form[i].name+'":"'+form[i].list[j]+'"\'>'+form[i].list[j]+'<small>'+form[i].title+'</small></a></li>');
							}
						}
					form[i].html += '</select>';

				}else{
					if (form[i].type == null) {
						form[i].type = 'text';
					}
					form[i].html = '<input type="'+form[i].type+'" id="'+form[i].name+'" placeholder="'+form[i].title+'">';

					if (form[i].searchable) {
						$('#searchable').append('<li><a href="#" data-search="'+form[i].name+'">'+form[i].name+'</a></li>');
					}
				}

				// append field
				$('#form').append(
					'<div class="control-group">' +
						'<label class="control-label" for="'+form[i].name+'">'+form[i].title+'</label>' +
						'<div class="controls">' +
							form[i].html +
						'</div>' +
					'</div>'
				);
			}

		},

		/**
		 * load.people
		 * Fill in people list
		 * @params (object) 
		 */
		people: function(people) {"use strict";
			$('#people').html('');
			for (var i in people) {
				$('#people').append(
					'<li><a data-id="'+people[i].id+'" href="#">'+people[i].name+'<small>'+((people[i].form.title)?people[i].form.title:'')+'</small></a></li>'
				);
			}
			// Now bind event
			$('#people a').on('click', function(){
				crmx.get($(this).data('id'));
				return false;
			});
		},

		/**
		 * load.comments
		 * Fills the comments for that person
		 * @params (object)
		 */
		comments: function(comments) {"use strict";
			$('#comments').html('');
			for(var i in comments) {
				$('#comments').append('<blockquote>'+comments[i].text.autoLink()+'<small><em class="easydate">'+comments[i].date+'</em> by <strong>'+comments[i].user+'</strong></small></blockquote>');
			}
			$(".easydate").easydate();
		}
	},// load


	/**
	 * refresh
	 * Asks the server for an updated list of people
	 */
	refresh: function() {"use strict";
		$('#s').val('');
		crmx.search('');
	},

	/**
	 * save
	 * Saves or Creates a person details
	 */
	save: function(){"use strict";
		crmx.notification('Loading&hellip;');
		var data = {
			id: $('#id').val(),
			name: $('#name').val(),
			title: $('#title').val()
		};

		for(var i in crmx.form) {
			data[crmx.form[i].name] = $('#'+crmx.form[i].name).val();
		}

		$.ajax({
			type: "POST",
			url: "save",
			data: data
		}).done(function( response ) {
			crmx.notification( response.message, response.status );
			if (response.status==='success') {
				$('.save').fadeOut();
				$('#delete').fadeIn();
				$('#commentbox').fadeIn();
				crmx.refresh();
				// If created a new person then select him
				if (response.id) {
					$('#id').val(response.id);
					$('#people li a[data-id='+response.id+']').parent().addClass('active');
				}
			}
		});
	},

	/**
	 * remove
	 * Deletes a person from the database
	 * @params (integer) ID of the person
	 */
	remove: function(id) {"use strict";
		if (!id) {return false;}
		if (confirm('Are you sure you want to delete this contact?')===true) {
			crmx.notification('Deleting&hellip;');
			$.ajax({
				type: "DELETE",
				url: "delete/"+id
			}).done(function( response ) {
				crmx.notification( response.message, response.status );
				if (response.status==='success') {
					// Clean up!
					$('#name').val('');
					$('#title').val('');
					for(var i in crmx.form) {
						$('#'+crmx.form[i].name).val('');
					}
					$('.save').fadeOut();
					$('#delete').fadeOut();
					$('#commentbox').fadeOut();
					crmx.search('');
				}else{
					crmx.notification( response.message, response.status );
				}
			});
		}
	},

	/**
	 * comment
	 * Add a comment to the person
	 * @params (integer) ID of the person
	 * @params (string) Comment
	 */
	comment: function(id, comment) {"use strict";
		crmx.notification('Commenting&hellip;');
		$.ajax({
			type: "POST",
			url: "comment",
			data: {id: id, comment: comment}
		}).done(function( response ) {
			if (response.status!=='error') {
				crmx.notification();
				$('#c').val('');
				crmx.load.comments(response);
			}else{
				crmx.notification( response.message, response.status );
			}
		});
	},

	/**
	 * get
	 * Gets a person's information
	 * @params (integer) ID of the person
	 */
	get: function(id) {"use strict";
		crmx.notification('Loading&hellip;');
		$.ajax({
			type: "GET",
			url: "get/"+id
		}).done(function( response ) {
			if (response.status!=='error') {
				crmx.notification();

				// Clean up!
				$('#name').val(response.name);
				$('#id').val(response.id);
				$('#title').val(response.form.title);
				for(var i in crmx.form) {
					$('#'+crmx.form[i].name).val( response.form[crmx.form[i].name] );
				}

				$('.save').fadeOut();
				$('#delete').fadeIn();
				$('#commentbox').fadeIn();

				$('#people li').removeClass('active');
				$('#people li a[data-id='+id+']').parent().addClass('active');

				crmx.load.comments(response.comments);

			}else{
				crmx.notification( response.message, response.status );
			}
		});
	},

	/**
	 * search
	 * Searches in the database
	 * @params (string) Search query
	 */
	search: function(query) {"use strict";
		$('#people').prepend('<li class="nav-header">Searching&hellip;</li>');
		$.ajax({
			type: "GET",
			url: "search/"+query
		}).done(function( response ) {
			if (!response.status) {
				crmx.load.people(response);
			}else{
				crmx.notification( response.message, response.status );
			}
		});
	},

	/**
	 * notification
	 * Shows a notification
	 * @params (string) Message or empty to hide notification
	 * @params (string) Can be ok, error or info
	 */
	notification: function(message, type) {"use strict";
		if (message==null) {
			$('#notification').fadeOut('fast');
		}else{
			$('#notification')
				.html( ((type)?'<strong>'+type+'</strong> ':'')+message)
				.attr('class', 'alert alert-'+type)
				.fadeIn(500, function(){
					$(this).fadeOut(10000);
				})
				.click(function(){
					$(this).fadeOut(500);
				});
		}
	},


	/**
	 * updateui
	 * Shows or hides buttons to unclutter UI
	 */
	updateui: function() {"use strict";
		if ($('#id').val().length>0) {
			$('.save').fadeIn().html('Save');
			$('#delete').fadeIn();
		}else{
			$('.save').fadeIn().html('Create new');
			$('#delete').fadeOut();
		}
	},


	/**
	 * run
	 * Starts the app, loads people and form, binds events
	 */
	run: function() {"use strict";

		crmx.load.people(crmx.people);
		crmx.load.form(crmx.form);

		// Event Binding
		$('.save').on('click', function(){
			crmx.save();
			return false;
		});

		$('#delete').on('click', function(){
			crmx.remove( $('#id').val() );
			return false;
		});

		// Update UI (can be optimised)
		$('#form').on('change', 'select', function() {crmx.updateui();});
		$('#main').on('input paste', 'input', function() {crmx.updateui();});
		$('#name').on('input paste', function() {crmx.updateui();});
		$('#title').on('input paste', function() {crmx.updateui();});

		$('#s').on('input paste', function() {
			if (crmx.timer===false) {
				crmx.timer = true;
				var t = setTimeout(function(){ crmx.search($('#s').val());crmx.timer=false; }, 500);
			}
		});

		$('#c_button').on('click', function(){
			if ($('#id').val().length<1) {
				crmx.notification('Select a contact first', 'error');
				return false;
			}
			if ($('#c').val().length<1) {
				crmx.notification('Enter a comment first', 'error');
				return false;
			}
			crmx.comment($('#id').val(), $('#c').val().replace(/\n/g, '<br>'));
		});

		$('#searchable').click(function(e){
			var target = $(e.target); // the child that fired the original click
		
			crmx.search(target.data('search'));
		
		});


		$('#s').focus();
	}


}; // crmx

