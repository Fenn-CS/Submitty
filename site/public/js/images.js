$(document).ready(function(){

$('.no-image-available').each(function(i, obj) {
	var student_name = $(obj).data('student-name');
	// Identicon requires a hash of at least 15 chars
	// So to prevent short names from breaking it, the name is salted.
	// This would ensure resulting hashes are longer than 15 chars.
	student_name = student_name+'0123456789_abcdefghijklmnopqrstuvwxyz';
    // create a base64 encoded PNG
    var data = new Identicon(btoa(student_name), 210).toString();
    // update no image tag.
    $(obj).replaceWith('<img width=210 height=210 src="data:image/png;base64,' + data + '">');
});

});