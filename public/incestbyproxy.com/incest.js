  function getFlashElement(elName)
  	{
  	if(window.document[elName])
  		return window.document[elName];
  	else if(navigator.appName.indexof("Microsoft Internet") == -1)
  		{
  		if(document.embeds && document.embeds[elName])
  			return document.embeds[elName];
  		}
  	else
  		return document.getElementById(elName);

  	}

  function loadMovie()
  	{
	windowref = window.open('http://www.incestbyproxy.com/HarlequinAd1.html','HarlequinMovie','scrollbars=no, toolbar = 0, width = 760, height = 550, left = 25, top = 200');
	}
  function closeMovie()
  	{
	//var theMovie = getFlashElement('HarlequinAd1');
        var containingDiv  = document.getElementById('movie');
        // theMovie.StopPlay();
 	containingDiv.innerHTML = "";
 	containingDiv.style.width = 0;
 	containingDiv.style.height = 0;
 	}
  function openMovie()
  	{
  	var code = '';
    document.getElementById('movie').innerHTML = code;

  	}