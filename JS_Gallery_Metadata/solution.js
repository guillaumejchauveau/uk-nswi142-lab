function preprocessGalleryData(imgData)
{
	/*
	 * Your code goes here...
	 */
	
	return [ imgData ];
}


// In nodejs, this is the way how export is performed.
// In browser, module has to be a global varibale object.
module.exports = { preprocessGalleryData };
