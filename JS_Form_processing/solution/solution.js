/**
 * Example of a local function which is not exported. You may use it internally in processFormData().
 * This function verifies the base URL (i.e., the URL prefix) and returns true if it is valid.
 * @param {*} url
 */
function verifyBaseUrl (url) {
	return Boolean(url.match(/^https:\/\/[-a-z0-9._]+([:][0-9]+)?(\/[-a-z0-9._/]*)?$/i))
}

/**
 * Example of a local function which is not exported. You may use it internally in processFormData().
 * This function verifies the relative URL (i.e., the URL suffix) and returns true if it is valid.
 * @param {*} url
 */
function verifyRelativeUrl (url) {
	return Boolean(url.match(/^[-a-z0-9_/]*([?]([-a-z0-9_\]\[]+=[^&=]*&)*([-a-z0-9_\]\[]+=[^&=?#]*)?)?$/i))
}

function verifyPadding (numberStr, min, max, padding = true) {
	const l = max.toString().length
	const number = parseInt(numberStr)
	return (!padding || numberStr.length === l) && !isNaN(number) && number >= min && number <= max
}

function processTime (timeStr, parse = true) {
	const times = []
	for (const time of timeStr.split('-')) {
		const digits = []
		for (const digit of time.trim().split(':')) {
			if (parse) {
				digits.push(parseInt(digit))
			} else {
				digits.push(digit)
			}
		}
		times.push(digits)
	}
	return times
}

function processDate (dateStr, parse = true) {
	const date = {}
	let digits = []
	let sep
	for (sep of './-') {
		digits = dateStr.split(sep)
		if (digits.length !== 1) {
			break
		}
	}
	if (digits.length !== 3) {
		return {}
	}
	switch (sep) {
		case '.':
			date.day = digits[0]
			date.month = digits[1]
			date.year = digits[2]
			break
		case '/':
			date.day = digits[1]
			date.month = digits[0]
			date.year = digits[2]
			break
		case '-':
			date.day = digits[2]
			date.month = digits[1]
			date.year = digits[0]
			break
	}
	if (parse) {
		date.day = parseInt(date.day)
		date.month = parseInt(date.month)
		date.year = parseInt(date.year)
	}
	date.sep = sep
	return date
}

function verifyTimes (times) {
	if (times.length < 1 || times.length > 2) {
		return false
	}
	for (const time of times) {
		if (time.length < 2 || time.length > 3) {
			return false
		}
		const hours = time[0]
		const hourNumber = parseInt(hours)
		if (hours !== hourNumber.toString() || isNaN(hourNumber) || hourNumber < 0 || hourNumber > 23) {
			return false
		}

		for (const digit of time.slice(1)) {
			if (!verifyPadding(digit, 0, 59)) {
				return false
			}
		}
	}
	if (times.length === 2) {
		const timesInt = times.map(digits => {
			let sum = parseInt(digits[0]) * 3600 + parseInt(digits[1]) * 60
			if (digits.length === 3) {
				sum += parseInt(digits[2])
			}
			return sum
		})
		if (timesInt[0] > timesInt[1]) {
			return false
		}
	}
	return true
}

function verifyDate (date) {
	if (Object.values(date).length !== 4) {
		return false
	}
	const pad = date.sep === '-'
	return verifyPadding(date.day, 1, 31, pad) &&
		verifyPadding(date.month, 1, 12, pad) &&
		verifyPadding(date.year, 1000, 9999)
}

function getCollectionErrors (collection) {
	const errors = []
	let canBeInterval = false
	const repeat = parseInt(collection.repeat)
	if (isNaN(repeat) || repeat < 1 || repeat > 100 || repeat.toString() !== collection.repeat) {
		errors['repeat'] = 'Invalid repeat count. Repeat count must be a number between 1 and 100.'
	} else if (repeat > 1) {
		canBeInterval = true
	}
	if (['GET', 'POST', 'PUT', 'DELETE'].indexOf(collection.method) === -1) {
		errors['method'] = 'Invalid'
	}
	if (collection.body.length !== 0) {
		try {
			JSON.parse(collection.body)
		} catch (e) {
			if (e instanceof SyntaxError) {
				errors['body'] = 'The body must be either valid JSON or empty.'
			} else {
				throw e
			}
		}

	}
	if (!verifyRelativeUrl(collection.url)) {
		errors['url'] = 'Invalid URL suffix format.'
	}

	const times = processTime(collection.time, false)
	if (!verifyTimes(times)) {
		errors['time'] = 'Invalid time or time interval format. Time must be in h:mm or h:mm:ss format, time interval are two times separated by dash.'
	}
	if (!canBeInterval && times.length !== 1) {
		errors['time'] = 'Time interval is not allowed when there is only one repetition set.'
	}
	if (!verifyDate(processDate(collection.date, false))) {
		errors['date'] = 'Invalid date format. Allowed formats a are d.m.yyyy, m/d/yyyy, and yyyy-mm-dd.'
	}
	return errors
}

/**
 * Main exported function that process the form and yields the sanitized data (or errors).
 * @param {*} formData Input data as FormData instance.
 * @param {*} errors Object which collects errors (if any).
 * @return string Serialized JSON containing sanitized form data.
 */
function processFormData (formData, errors) {
	const fieldCounter = {}
	const collections = {}
	let baseUrl = ''
	let data
	if (typeof formData._flattenItems === 'function') {
		data = formData._flattenItems()
	} else {
		data = formData.entries()
	}
	for (const [fieldName, fieldValue] of data) {
		if (!fieldCounter.hasOwnProperty(fieldName)) {
			fieldCounter[fieldName] = -1
		}
		fieldCounter[fieldName]++
		const currentCollectionIndex = fieldCounter[fieldName]
		if (!collections.hasOwnProperty(currentCollectionIndex)) {
			collections[currentCollectionIndex] = {}
		}
		if (fieldName === 'url_base') {
			baseUrl = fieldValue
		} else {
			collections[currentCollectionIndex][fieldName] = fieldValue
		}
	}

	if (!verifyBaseUrl(baseUrl)) {
		errors['url_base'] = 'Invalid URL format.'
	}

	for (const [collectionId, collection] of Object.entries(collections)) {
		for (const [field, message] of Object.entries(getCollectionErrors(collection))) {
			if (!errors.hasOwnProperty(field)) {
				errors[field] = {}
			}
			errors[field][collectionId] = message
		}
	}

	if (Object.values(errors).length !== 0) {
		return null
	}

	return JSON.stringify(Object.values(collections).map(collection => {
		collection.repeat = parseInt(collection.repeat)
		collection.url = baseUrl + collection.url
		if (collection.body !== '') {
			collection.body = JSON.parse(collection.body)
		} else {
			collection.body = {}
		}
		const date = processDate(collection.date)
		collection.date = Date.UTC(date.year, date.month-1, date.day) / 1000
		const times = processTime(collection.time).map(digits => {
			let sum = digits[0] * 3600 + digits[1] * 60
			if (digits.length === 3) {
				sum += digits[2]
			}
			return sum
		})

		if (times.length === 1) {
			collection.time = times[0]
		} else {
			collection.time = {
				from: times[0],
				to: times[1]
			}
		}
		return collection
	}))
}

// In nodejs, this is the way how export is performed.
// In browser, module has to be a global variable object.
module.exports = { processFormData }
