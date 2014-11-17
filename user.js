$( document ).ready(function(){

    userInputs = $('form.user input[type="submit"]');
    userInputs.prop('disabled',true);
    
    var USER = new Object();
    USER.enableButtons = function(){
        userInputs.prop('disabled',false);
    }
    setTimeout(USER.enableButtons,3000);
    

});