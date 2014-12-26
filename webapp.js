$(document).ready(function(){
    
    fixLinks(); // External links get new window.

    var clickToSelects = $('.click_to_select');
    clickToSelects.prop('title','Right-click to select this, then choose COPY.');
    clickToSelects.mouseup(function(event){
        target = event.target;
        if (document.body.createTextRange) {
            range = document.body.createTextRange();
            range.moveToElementText(target);
            range.select();
        }else if(window.getSelection){
            selection = window.getSelection();        
            range = document.createRange();
            range.selectNodeContents(target);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    });
    
});


//// Generic components good for all websites (steal this stuff, you know you want it).

function fixLinks(){
// External links get new window.
// Active links get different style.
// Updated: 2014-11-22-1039

    var eachLink, linkClass, matchArgs, i, ii, iii, thisOne;
    var location = window.location;
    if(location.pathname.match(/index\.php/i)){ // Redirect w/o filename.
        window.location = location.href.replace(/index\.php[^\?&\/]?/i,'');
    }
    // Flatten paths and searches(query).
    location.pathFixed = location.pathname.replace(/^\/*/ig,'/').replace(/\/*$/ig,'');
    location.args = location.search.replace(/^\?/i,'').split('&');
    i=document.links.length; while(i--){ // Walk links.
        eachLink = document.links[i];
        eachLink.pathFixed = eachLink.pathname.replace(/^\/*/ig,'/').replace(/\/*$/ig,'');
        // External links.
        if( eachLink.getAttributeNode('class') ){
            linkClass = ''+eachLink.getAttributeNode('class').value;
                 // Why not className, for nodes altered by scripting?
        }else{ linkClass = ''; }
        if( // Determine externals.
            linkClass.match(/(^|\s+)extlink(\s+|$)/i) || // Forced external.
            (
                !linkClass.match(/(^|\s+)intlink(\s+|$)/i) && // NOT forced internal.
                (
                    eachLink.hostname !== location.hostname // Diff host.
//                    || eachLink.pathFixed !== location.pathFixed // Diff path.
                )
            )
        ){
            eachLink.onclick = function(){ window.open(this.href); return false; };
        }
        
        // Active links get diff style.
        if(
            eachLink.protocol === location.protocol
            && eachLink.hostname === location.hostname
            && eachLink.pathFixed === location.pathFixed
        ){
            if(eachLink.search || location.search){
                matchArgs = 0;
                eachLink.args = eachLink.search.replace(/^\?/i,'').split('&');
                ii=0;while(ii < eachLink.args.length){
                    iii=0;while(iii < location.args.length){
                        if( location.args[iii] === eachLink.args[ii] ){
                            matchArgs++;
                            break; // Only one match necessary.
                        }
                    iii++;}
                ii++;}
                // ALL of the link's args must match the location...
                if( matchArgs !== eachLink.args.length ){ continue; }
            }
            eachLink.className += ' active';
            eachLink.style.fontStyle = 'italic';
        }
    }
    // Done walking links.
}
